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
    // (require.php's floor, else config.platform.php), which the sync keeps in
    // step with maho.
    ->withPhpSets()
    // The sets above are taken wholesale, unlike maho's own config, which pins
    // them to an old target and hand-picks the newer rules by name. These three
    // are what that policy guards against, and they are wrong for a published
    // package:
    //
    //  - AddTypeToConst emits `const string FOO`. That is new syntax, not a
    //    rewrite, so a repo that declares no require.php floor (such as
    //    maho-composer-plugin) would ship code its own metadata never promised.
    //    A composer plugin also runs on the user's PHP, not on the platform the
    //    project resolved against.
    //  - ReadOnlyClass / ReadOnlyProperty change the contract, not the code: a
    //    readonly class cannot be extended by a normal child, and a readonly
    //    property cannot be written from one. Maho modules exist to be extended.
    //
    // Everything else in the sets is a safe rewrite, so keep the derivation.
    ->withSkip([
        Rector\Php81\Rector\Property\ReadOnlyPropertyRector::class,
        Rector\Php82\Rector\Class_\ReadOnlyClassRector::class,
        Rector\Php83\Rector\ClassConst\AddTypeToConstRector::class,
    ])
    ->withRules([
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\SimplifyTautologyTernaryRector::class,
        CodingStyle\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class,
        DeadCode\ClassMethod\RemoveUselessParamTagRector::class,
        DeadCode\ClassMethod\RemoveUselessReturnTagRector::class,
        DeadCode\MethodCall\RemoveNullArgOnNullDefaultParamRector::class,
        DeadCode\Property\RemoveUselessVarTagRector::class,
        EarlyReturn\If_\RemoveAlwaysElseRector::class,
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        TypeDeclaration\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
    ]);
