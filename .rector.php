<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector as CodeQuality;
use Rector\CodingStyle\Rector as CodingStyle;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector as DeadCode;
use Rector\EarlyReturn\Rector as EarlyReturn;
use Rector\TypeDeclaration\Rector as TypeDeclaration;

// Shared by every repo that consumes the org baseline. Only standard Rector
// rules, so it needs no extra dependency: maho's own .rector.php (with the
// Maho\Rector\* rules and the Varien->Maho migration) stays in maho and is not
// synced. Only the paths that exist in a given repo are scanned, so the one
// config works for app-only modules and the infra tool's src/ alike.
return RectorConfig::configure()
    ->withPaths(array_values(array_merge(
        array_filter([
            __DIR__ . '/app',
            __DIR__ . '/lib',
            __DIR__ . '/public',
            __DIR__ . '/src',
        ], 'is_dir'),
        // Root-level entry points (e.g. the infra tool's sync.php / config.php).
        // glob skips dotfiles, so this very config file isn't included.
        glob(__DIR__ . '/*.php') ?: [],
    )))
    // No argument: Rector reads the target PHP version from composer.json
    // (require.php's floor, else config.platform.php), kept at 8.3 by the sync.
    ->withPhpSets()
    ->withRules([
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\Foreach_\UnusedForeachValueToArrayKeysRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\SimplifyTautologyTernaryRector::class,
        CodeQuality\Ternary\SwitchNegatedTernaryRector::class,
        CodingStyle\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class,
        DeadCode\ClassMethod\RemoveUselessParamTagRector::class,
        DeadCode\ClassMethod\RemoveUselessReturnTagRector::class,
        DeadCode\MethodCall\RemoveNullArgOnNullDefaultParamRector::class,
        DeadCode\Property\RemoveUselessVarTagRector::class,
        EarlyReturn\If_\ChangeNestedIfsToEarlyReturnRector::class,
        EarlyReturn\If_\RemoveAlwaysElseRector::class,
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        TypeDeclaration\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
    ])
    ->withConfiguredRule(Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector::class, [
        'sensitive_parameters' => [
            'token', 'apiKey', 'email', 'useremail', 'username', 'password',
        ],
    ]);
