<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Handler\FileOperations;
use PHPUnit\Framework\TestCase;

/**
 * Behavioral tests of parent resolution with isolated native-function dependencies.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FileOperationsCanonicalizeTest extends TestCase
{
    /** @return void Verifies parent and ancestor aliases follow a trusted deployment switch. */
    public function testRefreshesRetargetedParentAndAncestorAliases(): void
    {
        require_once __DIR__ . '/Support/PathFunctions.php';

        try {
            foreach (['', '/logs'] as $suffix) {
                $GLOBALS['coisa_file_operations_paths'] = [
                    'aliases' => ['/owned/current' => '/owned/v1'],
                    'directories' => [
                        '/owned/v1' => '/owned/v1', '/owned/v2' => '/owned/v2',
                        '/owned/v1/logs' => '/owned/v1/logs', '/owned/v2/logs' => '/owned/v2/logs',
                    ],
                    'cache' => [],
                ];
                $operations = new FileOperations();
                $filename = '/owned/current' . $suffix . '/debug.log';
                self::assertSame('/owned/v1' . $suffix . '/debug.log', $operations->canonicalize($filename));

                $GLOBALS['coisa_file_operations_paths']['aliases']['/owned/current'] = '/owned/v2';
                self::assertSame('/owned/v2' . $suffix . '/debug.log', $operations->canonicalize($filename));
            }
        } finally {
            unset($GLOBALS['coisa_file_operations_paths']);
        }
    }

    /** @return void Verifies ordinary aliases and relative filenames retain the final component. */
    public function testPreservesOrdinaryAliasesAndFinalComponent(): void
    {
        require_once __DIR__ . '/Support/PathFunctions.php';
        $GLOBALS['coisa_file_operations_paths'] = [
            'aliases' => ['/var' => '/private/var'],
            'directories' => ['/private/var/application' => '/private/var/application', '.' => '/owned'],
            'cache' => [],
        ];

        try {
            $operations = new FileOperations();
            self::assertSame(
                '/private/var/application/debug.log',
                $operations->canonicalize('/var/application/debug.log'),
            );
            self::assertSame('/owned/0', $operations->canonicalize('0'));
            self::assertSame('/owned/app:debug.log', $operations->canonicalize('app:debug.log'));
        } finally {
            unset($GLOBALS['coisa_file_operations_paths']);
        }
    }

    /** @return void Verifies an unresolved parent fails before returning a destination. */
    public function testRejectsMissingParent(): void
    {
        require_once __DIR__ . '/Support/PathFunctions.php';
        $GLOBALS['coisa_file_operations_paths'] = ['aliases' => [], 'directories' => [], 'cache' => []];
        $operations = new FileOperations();
        $this->expectException(\UnexpectedValueException::class);

        try {
            $operations->canonicalize('/missing/debug.log');
        } finally {
            unset($GLOBALS['coisa_file_operations_paths']);
        }
    }
}
