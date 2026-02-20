<?php

use App\Services\Database\SqlFileRunner;
use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\SqlLoggerInterface;

beforeEach(function () {
    $this->connection = Mockery::mock(DatabaseConnectionInterface::class);
    $this->logger = Mockery::mock(SqlLoggerInterface::class);
    $this->logger->shouldReceive('logFileStart')->andReturn(null);
    $this->logger->shouldReceive('log')->andReturn(null);
    $this->logger->shouldReceive('logError')->andReturn(null);
});

afterEach(function () {
    Mockery::close();
});

function makeRunner(string $sqlDir): SqlFileRunner
{
    return new SqlFileRunner(
        test()->connection,
        test()->logger,
        $sqlDir,
    );
}

test('splitStatements splits simple semicolon-delimited SQL', function () {
    $runner = makeRunner('/tmp');

    $sql = "SELECT 1;\nSELECT 2;\nSELECT 3;";
    $statements = $runner->splitStatements($sql);

    expect($statements)->toHaveCount(3);
    expect($statements[0])->toBe('SELECT 1');
    expect($statements[1])->toBe('SELECT 2');
    expect($statements[2])->toBe('SELECT 3');
});

test('splitStatements handles DELIMITER $$ blocks', function () {
    $runner = makeRunner('/tmp');

    $sql = <<<SQL
    DELIMITER \$\$
    CREATE PROCEDURE test_proc()
    BEGIN
        SELECT 1;
        SELECT 2;
    END\$\$
    DELIMITER ;
    SELECT 3;
    SQL;

    $statements = $runner->splitStatements($sql);

    expect($statements)->toHaveCount(2);
    expect($statements[0])->toContain('CREATE PROCEDURE');
    expect($statements[1])->toBe('SELECT 3');
});

test('splitStatements ignores empty statements', function () {
    $runner = makeRunner('/tmp');

    $sql = "SELECT 1;\n\n;\n\nSELECT 2;";
    $statements = $runner->splitStatements($sql);

    $nonEmpty = array_values(array_filter($statements, fn($s) => $s !== ''));
    expect($nonEmpty)->toHaveCount(2);
});

test('splitStatements handles multiline statements', function () {
    $runner = makeRunner('/tmp');

    $sql = "ALTER TABLE `foo`\nDROP COLUMN `bar`,\nDROP COLUMN `baz`;";
    $statements = $runner->splitStatements($sql);

    expect($statements)->toHaveCount(1);
    expect($statements[0])->toContain('ALTER TABLE');
    expect($statements[0])->toContain('DROP COLUMN');
});

test('splitStatements handles leading line comments', function () {
    $runner = makeRunner('/tmp');

    // Leading -- comments are part of the accumulated buffer but don't affect splitting
    $sql = "-- This is a comment\nSELECT 1;\n-- Another comment\nSELECT 2;";
    $statements = $runner->splitStatements($sql);

    $nonEmpty = array_values(array_filter($statements, fn($s) => $s !== ''));
    expect($nonEmpty)->toHaveCount(2);
    expect($nonEmpty[0])->toContain('SELECT 1');
    expect($nonEmpty[1])->toContain('SELECT 2');
});

test('runAll returns failure result on SQL error', function () {
    $tempDir = sys_get_temp_dir() . '/sql_test_' . uniqid();
    mkdir($tempDir);
    file_put_contents($tempDir . '/01_test.sql', 'DROP TABLE `non_existent_table`;');

    $this->connection->shouldReceive('execute')
        ->andThrow(new \PDOException('Table does not exist'));

    $runner = makeRunner($tempDir);
    $results = $runner->runAll();

    expect($results)->toHaveCount(1);
    expect($results[0]->success)->toBeFalse();
    expect($results[0]->error)->toContain('Table does not exist');

    // Cleanup
    unlink($tempDir . '/01_test.sql');
    rmdir($tempDir);
});

test('runFrom skips files before the given number', function () {
    $tempDir = sys_get_temp_dir() . '/sql_test_' . uniqid();
    mkdir($tempDir);
    file_put_contents($tempDir . '/01_first.sql', 'SELECT 1;');
    file_put_contents($tempDir . '/02_second.sql', 'SELECT 2;');
    file_put_contents($tempDir . '/03_third.sql', 'SELECT 3;');

    $this->connection->shouldReceive('execute')->once()->andReturn(0);

    $runner = makeRunner($tempDir);
    $results = $runner->runFrom(3);

    expect($results)->toHaveCount(1);
    expect($results[0]->fileNumber)->toBe(3);
    expect($results[0]->filename)->toBe('03_third.sql');

    // Cleanup
    array_map('unlink', glob($tempDir . '/*.sql'));
    rmdir($tempDir);
});
