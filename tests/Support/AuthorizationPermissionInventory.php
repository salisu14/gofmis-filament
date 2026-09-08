<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Blade;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Source inventory, independent of the seeder's permission list. */
class AuthorizationPermissionInventory
{
    public const LEGACY_PERMISSIONS = [
        'user_access', 'user_create', 'user_edit', 'user_delete', 'role_access', 'role_edit',
        'imprest_view_transactions', 'imprest_create_transactions', 'imprest_edit_transactions',
        'imprest_delete_transactions', 'imprest_approve_transactions', 'imprest_void_transactions',
        'imprest_view_funds', 'imprest_reconcile_funds', 'imprest_replenish_funds',
        'view education verifications', 'edit education verifications',
        'approve education requests', 'reject education requests',
    ];

    public static function scan(array $directories = ['app', 'routes', 'bootstrap', 'config', 'resources/views']): array
    {
        $parser = (new ParserFactory)->createForHostVersion();
        $finder = new NodeFinder;
        $printer = new Standard;
        $permissions = $policyCalls = $unresolved = [];
        $policyAbilities = [];
        foreach (glob(base_path('app/Policies/*.php')) as $file) {
            foreach ($finder->findInstanceOf($parser->parse(file_get_contents($file)), Node\Stmt\ClassMethod::class) as $method) {
                $policyAbilities[$method->name->toString()] = true;
            }
        }

        foreach ($directories as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $path = substr($file->getPathname(), strlen(base_path()) + 1);
                $code = file_get_contents($file->getPathname());
                if (str_ends_with($path, '.blade.php')) {
                    $code = Blade::compileString($code);
                }
                $nodes = $parser->parse($code);
                foreach ($finder->find($nodes, fn (Node $node): bool => $node instanceof Node\Expr\MethodCall
                    || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall) as $call) {
                    if (! $call->name instanceof Node\Identifier) {
                        continue;
                    }
                    $method = $call->name->toString();
                    $direct = in_array($method, ['hasPermissionTo', 'checkPermissionTo', 'hasAnyPermission', 'hasAllPermissions'], true);
                    if (! $direct && ! in_array($method, ['can', 'cannot', 'cant', 'canAny', 'canAll', 'allows', 'denies', 'authorize', 'check', 'any', 'none'], true)) {
                        continue;
                    }
                    // check()/any() also belong to Auth, Hash and query APIs.
                    if (in_array($method, ['check', 'any', 'none'], true)
                        && ! str_contains($printer->prettyPrintExpr($call), 'Gate')) {
                        continue;
                    }
                    $arguments = $call->getArgs();
                    $source = $path.':'.$call->getStartLine();
                    $names = isset($arguments[0]) ? self::literalNames($arguments[0]->value) : null;
                    if ($names === null) {
                        $unresolved[$source] = $printer->prettyPrintExpr($call);

                        continue;
                    }
                    foreach ($names as $name) {
                        // Reviewed policy dispatch: requires BOTH model arguments and
                        // an actual policy method. A bare can('update') is not excluded.
                        if (! $direct && isset($arguments[1]) && isset($policyAbilities[$name])) {
                            $policyCalls[$source] = $printer->prettyPrintExpr($call);
                        } else {
                            $permissions[$name][] = $source;
                        }
                    }
                }
                foreach ($finder->findInstanceOf($nodes, Node\Scalar\String_::class) as $string) {
                    if (preg_match('/^(permission|can):([^,]+)(?:,.*)?$/', $string->value, $matches)) {
                        $names = explode('|', $matches[2]);
                        foreach ($names as $name) {
                            $source = $path.':'.$string->getStartLine();
                            if ($matches[1] === 'can' && str_contains($string->value, ',') && isset($policyAbilities[$name])) {
                                $policyCalls[$source] = $string->value;
                            } else {
                                $permissions[$name][] = $source;
                            }
                        }
                    }
                }
            }
        }
        ksort($permissions);
        ksort($policyCalls);
        ksort($unresolved);

        return ['permissions' => $permissions, 'policy_calls' => $policyCalls, 'unresolved' => $unresolved];
    }

    /** Declared canonical input, also compared with actual seeded rows in tests. */
    public static function canonicalPermissions(): array
    {
        $parser = (new ParserFactory)->createForHostVersion();
        $nodes = $parser->parse(file_get_contents(database_path('seeders/RolesAndPermissionsSeeder.php')));
        $assignment = (new NodeFinder)->findFirst($nodes, fn (Node $node): bool => $node instanceof Node\Expr\Assign
            && $node->var instanceof Node\Expr\Variable && $node->var->name === 'permissions');
        $names = $assignment ? self::literalNames($assignment->expr) : null;
        if ($names === null) {
            throw new \RuntimeException('Canonical permission declaration changed; review inventory extraction.');
        }
        sort($names);

        return $names;
    }

    private static function literalNames(Node $node): ?array
    {
        if ($node instanceof Node\Scalar\String_) {
            return [$node->value];
        }
        if ($node instanceof Node\Expr\Array_) {
            $names = [];
            foreach ($node->items as $item) {
                if ($item === null || $item->unpack || ($values = self::literalNames($item->value)) === null) {
                    return null;
                }
                array_push($names, ...$values);
            }

            return $names;
        }

        return null;
    }
}
