<?php

namespace OpenAdmin\Admin\Helpers\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use OpenAdmin\Admin\Auth\Database\Menu;
use OpenAdmin\Admin\Helpers\Model\Scaffold;
use OpenAdmin\Admin\Helpers\Model\ScaffoldDetail;
use OpenAdmin\Admin\Helpers\Scaffold\MigrationCreator;
use OpenAdmin\Admin\Helpers\Scaffold\ModelCreator;
use OpenAdmin\Admin\Helpers\Scaffold\ControllerCreator;
use OpenAdmin\Admin\Layout\Content;

class ScaffoldController extends Controller
{
    public function index(Content $content)
    {
        $content->header('Scaffold');

        $dbTypes = [
            'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
            'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
            'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
            'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
            'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
        ];

        $action = URL::current();

        $content->row(view('open-admin-helpers::scaffold', compact('dbTypes', 'action')));

        return $content;
    }


    public function edit($id, Content $content)
    {
        $scaffold = Scaffold::with('details')->findOrFail($id);

        $dbTypes = [
            'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
            'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
            'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
            'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
            'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
        ];

        $action = route('scaffold.update', $scaffold->id);

        return $content
            ->header('Edit Scaffold')
            ->row(view('open-admin-helpers::scaffold', compact('scaffold', 'dbTypes', 'action')));
    }


    public function store(Request $request)
    {
        $request->validate([
            'table_name' => 'required|string',
            'fields' => 'required|array',
        ]);

        [$scaffold, $paths, $message] = $this->saveScaffold($request);

        return $this->backWithSuccess($paths, $message);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'table_name' => 'required|string',
            'fields' => 'required|array',
        ]);

        $scaffold = Scaffold::findOrFail($id);

        [$scaffold, $paths, $message] = $this->saveScaffold($request, $scaffold);

        admin_toastr('Scaffold updated successfully', 'success');
        return redirect()->route('scaffold.edit', $id);
    }

    protected function saveScaffold(Request $request, Scaffold $scaffold = null)
    {
        $paths = [];
        $message = '';

        DB::transaction(function () use ($request, &$scaffold) {
            if (!$scaffold) {
                $scaffold = new Scaffold();
            }

            $scaffold->fill([
                'table_name' => $request->input('table_name'),
                'model_name' => $request->input('model_name'),
                'controller_name' => $request->input('controller_name'),
                'create_options' => $request->input('create', []),
                'primary_key' => $request->input('primary_key', 'id'),
                'timestamps' => $request->has('timestamps'),
                'soft_deletes' => $request->has('soft_deletes'),
            ])->save();

            // Remove old details if updating
            if ($scaffold->exists) {
                $scaffold->details()->delete();
            }

            foreach ($request->input('fields', []) as $index => $field) {
                $scaffold->details()->create([
                    'name' => $field['name'] ?? null,
                    'type' => $field['type'] ?? null,
                    'nullable' => isset($field['nullable']),
                    'key' => $field['key'] ?? null,
                    'default' => $field['default'] ?? null,
                    'comment' => $field['comment'] ?? null,
                    'order' => $index,
                ]);
            }
        });

        // File generation
        try {
            // 1. Model
            if (in_array('model', $request->get('create'))) {
                $modelPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('model_name'))) . '.php');
                $this->backupIfExists($modelPath);

                $paths['model'] = (new ModelCreator($request->get('table_name'), $request->get('model_name')))
                    ->create(
                        $request->get('primary_key'),
                        $request->get('timestamps') == 'on' || $request->has('timestamps'),
                        $request->get('soft_deletes') == 'on' || $request->has('soft_deletes')
                    );
            }

            // 2. Migration
            if (in_array('migration', $request->get('create'))) {
                $tableName = $request->get('table_name');
                $migrationName = 'create_' . $tableName . '_table';
                $migrationFiles = glob(database_path('migrations/*_' . $migrationName . '.php'));
                foreach ($migrationFiles as $file) {
                    $this->backupIfExists($file);
                }

                $paths['migration'] = (new MigrationCreator(app('files'), '/'))->buildBluePrint(
                    $request->get('fields'),
                    $request->get('primary_key', 'id'),
                    $request->get('timestamps') == 'on' || $request->has('timestamps'),
                    $request->get('soft_deletes') == 'on' || $request->has('soft_deletes')
                )->create($migrationName, database_path('migrations'), $tableName);
            }

            // 3. Controller
            if (in_array('controller', $request->get('create'))) {
                $controllerPath = app_path(str_replace('\\', '/', str_replace('App\\', '', $request->get('controller_name'))) . '.php');
                $this->backupIfExists($controllerPath);

                $paths['controller'] = (new ControllerCreator($request->get('controller_name')))
                    ->create($request->get('model_name'), $request->get('fields'));
            }

            // 4. Migrate DB
            if (in_array('migrate', $request->get('create'))) {
                $table = $request->input('table_name');
                $connection = config('database.default');
                $schema = DB::connection($connection)->getSchemaBuilder();
                $shouldMigrate = true;

                if ($schema->hasTable($table)) {
                    if (in_array('recreate_table', $request->get('create'))) {
                        $schema->drop($table);
                        $message .= "<br>Table <code>{$table}</code> dropped successfully.";
                    } else {
                        $shouldMigrate = false;
                        $message .= "<br>Migration skipped: Table <code>{$table}</code> already exists.";
                    }
                }

                if ($shouldMigrate) {
                    Artisan::call('migrate');
                    $message .= str_replace('Migrated:', '<br>Migrated:', Artisan::output());
                }
            }


            // 5. Menu
            if (in_array('menu_item', $request->get('create'))) {
                $route = $this->createMenuItem($request);
                $message .= '<br>Menu item created at: ' . $route;
            }

        } catch (\Exception $e) {
            Log::error('Scaffold generation failed', ['exception' => $e]);
            app('files')->delete($paths);
            //throw $e;
        }

        return [$scaffold, $paths, $message];
    }

    protected function backupIfExists($path)
    {
        if (file_exists($path)) {
            $backupDir = storage_path('scaffold_backups/' . date('YMd_His'));
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = basename($path);
            $newPath = $backupDir . '/' . $filename;

            rename($path, $newPath);
            Log::info("Backed up existing file: $path to $newPath");
        }
    }


    public function getRoute($request)
    {
        return Str::plural(Str::kebab(class_basename($request->get('model_name'))));
    }

    public function createMenuItem($request)
    {
        $route = $this->getRoute($request);
        $lastOrder = Menu::max('order');
        $root = [
            'parent_id' => 0,
            'order' => $lastOrder++,
            'title' => ucfirst($route),
            'icon' => 'icon-file',
            'uri' => $route,
        ];
        $root = Menu::create($root);

        return $route;
    }

    public function getControllerName($str)
    {
        return last(explode('\\', $str));
    }

    protected function backWithException(\Exception $exception)
    {
        $error = new MessageBag([
            'title' => 'Error',
            'message' => $exception->getMessage(),
        ]);

        return back()->withInput()->with(compact('error'));
    }

    protected function backWithSuccess($paths, $message)
    {
        $messages = [];

        foreach ($paths as $name => $path) {
            $messages[] = ucfirst($name) . ": $path";
        }

        $messages[] = $message;

        $success = new MessageBag([
            'title' => 'Success',
            'message' => implode('<br />', $messages),
        ]);

        return back()->with(compact('success'));
    }
}
