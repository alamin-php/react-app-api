<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:generate-crud';
    protected $signature = 'make:crud
        {model : Model name}
        {--fields= : Fields like name:string, status:enum(open,closed)}
        {--relations= : Relations like tasks:hasMany, user:belongsTo}';


    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';
    protected $description = 'Generate CRUD (Model, Migration, Controller, Requests, Views)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelName = ucfirst($this->argument('model'));
        $fields = $this->option('fields');
        $relations = $this->option('relations');

        $fieldArray = $this->parseFields($fields);
        // dd($fieldArray);

        $this->info("Generating CRUD for: {$modelName}");

        $this->generateModel($modelName, $fieldArray, $relations);
        $this->generateMigration($modelName, $fieldArray);
        $this->generateController($modelName);
        $this->generateRequests($modelName, $fieldArray);
        $this->generateViews($modelName);

        $this->info("CRUD for {$modelName} generated successfully!");
    }

    protected function parseFields($fields)
    {
        if (!$fields)
            return [];

        $fieldArray = [];

        preg_match_all('/(\w+):(\w+)(\((.*?)\))?/', $fields, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1]; // name
            $type = $match[2]; // string, text, enum
            $values = isset($match[4]) ? $match[4] : null; // open,closed (optional)

            if ($values) {
                $fieldArray[] = "{$name}:{$type}:{$values}";
            } else {
                $fieldArray[] = "{$name}:{$type}";
            }
        }

        return $fieldArray;
    }


    protected function parseFieldsForMigration($fieldArray)
    {
        $migrationFields = "";

        foreach ($fieldArray as $field) {
            if (empty($field) || strpos($field, ':') === false) {
                continue;
            }

            [$name, $type] = explode(':', $field, 2);

            // Check if it's an enum field
            if (str_starts_with($type, "enum:")) {
                // Remove 'enum:' part and split values
                $enumValues = str_replace('enum:', '', $type);  // open,closed
                $enumArray = explode(',', $enumValues);
                $enumArray = array_map(fn($v) => "'" . trim($v) . "'", $enumArray);  // Remove spaces
                $enumString = implode(', ', $enumArray);

                $migrationFields .= "\t\t\t\$table->enum('{$name}', [{$enumString}]);\n";
            } else {
                // Handle other types like string, text, integer, etc.
                $migrationFields .= "\t\t\t\$table->{$type}('{$name}');\n";
            }
        }

        return $migrationFields;
    }


    protected function generateModel($modelName, $fieldArray, $relations)
    {
        $fillable = $this->parseFieldsForFillable($fieldArray);

        $relationMethods = "";
        if ($relations) {
            $relationArray = explode(',', $relations);
            foreach ($relationArray as $relation) {
                [$name, $type] = explode(':', $relation);
                $relationMethods .= "\n\tpublic function {$name}()\n\t{\n";
                $relationMethods .= "\t\treturn \$this->{$type}(" . Str::studly(Str::singular($name)) . "::class);\n\t}\n";
            }
        }

        $stub = file_get_contents(app_path('Console/Commands/stubs/model.stub'));

        $stub = str_replace(
            ['{{model}}', '{{fillable}}', '{{relations}}'],
            [$modelName, $fillable, $relationMethods],
            $stub
        );

        File::put(app_path("Models/{$modelName}.php"), $stub);
    }

    protected function parseFieldsForFillable($fieldArray)
    {
        $fields = [];

        foreach ($fieldArray as $field) {
            if (str_contains($field, ':')) {
                [$name, $type] = explode(':', $field);
                $fields[] = "'{$name}'";
            }
        }

        return implode(', ', $fields);
    }


    protected function generateMigration($modelName, $fieldArray)
    {
        $tableName = Str::snake(Str::plural($modelName));
        $migrationFields = $this->parseFieldsForMigration($fieldArray);
        // dd($migrationFields);

        $this->call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName
        ]);

        $migrationPath = base_path("database/migrations/");
        $migrationFile = collect(File::files($migrationPath))
            ->last(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));

        if ($migrationFile) {
            $content = File::get($migrationFile->getRealPath());
            $content = str_replace('$table->id();', '$table->id();' . "\n" . $migrationFields, $content);
            File::put($migrationFile->getRealPath(), $content);
        }
    }

    protected function generateRoutes($modelName)
    {
        // Get the fully qualified controller class name
        $controllerNamespace = "App\\Http\\Controllers\\{$modelName}Controller";
        $resourceName = Str::lower(Str::plural($modelName));

        // Define the route string for the API and Web routes
        // Str::snake(Str::plural($modelName));
        $apiRoute = "Route::apiResource('{$resourceName}', {$controllerNamespace}::class);";
        $webRoute = "Route::resource('{$resourceName}', {$controllerNamespace}::class);";

        // Add API route to api.php
        $apiFilePath = base_path('routes/api.php');
        file_put_contents($apiFilePath, "\n" . $apiRoute, FILE_APPEND);

        // Add Web route to web.php
        $webFilePath = base_path('routes/web.php');
        file_put_contents($webFilePath, "\n" . $webRoute, FILE_APPEND);
    }


    protected function generateController($modelName)
    {
        // Load the controller stub template
        $controllerTemplate = file_get_contents(app_path('Console/Commands/stubs/controller.stub'));

        // Replace placeholders in the template
        $controllerTemplate = str_replace('{{modelName}}', $modelName, $controllerTemplate);
        $controllerTemplate = str_replace('{{modelVariable}}', strtolower($modelName), $controllerTemplate);  // Replaces with model instance variable, e.g. $employee

        // Save the generated controller to the correct path
        File::put(app_path("Http/Controllers/{$modelName}Controller.php"), $controllerTemplate);

        // Call a method to generate routes (if needed)
        $this->generateRoutes($modelName);
    }
    protected function generateRequests($modelName, $fields)
    {
        $path = app_path("Http/Requests/{$modelName}Request.php");
        $stub = file_get_contents(app_path('Console/Commands/stubs/request.stub'));
        $stub = str_replace('{{model}}', $modelName, $stub);

        $fieldsRules = '';
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                [$name, $type] = explode(':', $field, 2);

                // Fix enum type from enum:open,closed to enum(open,closed)
                if (str_starts_with($type, 'enum:')) {
                    $enumValues = substr($type, 5); // remove enum:
                    $type = "enum({$enumValues})";
                }

                // Handle enum fields properly
                if (preg_match('/enum\((.*)\)/', $type, $matches)) {
                    $enumValues = $matches[1];  // inside enum(...)

                    $enumValues = str_replace(' ', '', $enumValues); // Remove any space
                    $enumString = $enumValues; // No quotes needed

                    $fieldsRules .= "'{$name}' => 'required|in:{$enumString}',\n            ";
                } elseif ($type == 'string') {
                    $fieldsRules .= "'{$name}' => 'required|{$type}|max:255',\n            ";
                } elseif ($type == 'text') {
                    $fieldsRules .= "'{$name}' => 'nullable|string',\n            ";
                } else {
                    $fieldsRules .= "'{$name}' => 'required|{$type}',\n            ";
                }
            }
        }

        $stub = str_replace('{{fields_rules}}', rtrim($fieldsRules), $stub);
        File::put($path, $stub);
    }


    protected function generateViews($modelName)
    {
        $viewPath = resource_path("views/" . Str::snake(Str::plural($modelName)));
        File::ensureDirectoryExists($viewPath);
        File::put("{$viewPath}/index.blade.php", $this->getStub('index.stub', $modelName));
        File::put("{$viewPath}/create.blade.php", "<h1>Create {$modelName}</h1>");
        File::put("{$viewPath}/edit.blade.php", "<h1>Edit {$modelName}</h1>");
        File::put("{$viewPath}/show.blade.php", "<h1>Show {$modelName}</h1>");
    }

    protected function getStub($stub, $modelName)
    {
        // $stubPath = resource_path("Console/Commands/stubs/{$stub}");
        $stubPath = app_path("Console/Commands/stubs/{$stub}");
        $stubContent = file_get_contents($stubPath);

        return str_replace(
            ['{{ modelName }}', '{{ modelVariable }}', '{{ modelVariablePlural }}', '{{ routeName }}'],
            [$modelName, strtolower($modelName), Str::plural(strtolower($modelName)), Str::plural(strtolower($modelName))],
            $stubContent
        );
    }


}
