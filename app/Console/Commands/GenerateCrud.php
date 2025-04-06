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

        $this->info("Generating CRUD for: {$modelName}");

        $this->generateModel($modelName, $fieldArray, $relations);
        $this->generateMigration($modelName, $fieldArray);
        $this->generateController($modelName);
        $this->generateRequests($modelName);
        $this->generateViews($modelName);

        $this->info("CRUD for {$modelName} generated successfully!");
    }

    protected function parseFields($fields)
    {
        if (!$fields)
            return [];

        return array_map('trim', explode(',', $fields));
    }

    protected function parseFieldsForMigration($fieldArray)
    {
        $migrationFields = "";

        foreach ($fieldArray as $field) {
            if (!str_contains($field, ':'))
                continue;

            [$name, $type] = explode(':', $field, 2);  // Limit to 2 to avoid enum issue

            if (str_starts_with($type, "enum(")) {
                preg_match("/enum\((.*)\)/", $type, $matches);

                $enumValues = isset($matches[1]) ? $matches[1] : '';
                $migrationFields .= "\t\t\t\$table->enum('{$name}', [{$enumValues}]);\n";
            } else {
                $migrationFields .= "\t\t\t\$table->{$type}('{$name}');\n";
            }
        }

        return $migrationFields;
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

    protected function generateModel($modelName, $fieldArray, $relations)
    {
        $fillable = $this->parseFieldsForFillable($fieldArray);

        $relationMethods = "";
        if ($relations) {
            $relationArray = explode(',', $relations);
            foreach ($relationArray as $relation) {
                [$name, $type] = explode(':', $relation);
                $relationMethods .= "\n\tpublic function {$name}() {\n";
                $relationMethods .= "\t\treturn \$this->{$type}(" . Str::studly(Str::singular($name)) . "::class);\n\t}\n";
            }
        }

        $modelTemplate = "<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    use HasFactory;

    protected \$fillable = [{$fillable}];
    {$relationMethods}
}";

        File::put(app_path("Models/{$modelName}.php"), $modelTemplate);
    }

    protected function generateMigration($modelName, $fieldArray)
    {
        $tableName = Str::snake(Str::plural($modelName));
        $migrationFields = $this->parseFieldsForMigration($fieldArray);

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

    // protected function generateController($modelName)
    // {
    //     $this->call('make:controller', [
    //         'name' => "{$modelName}Controller",
    //         '--resource' => true,
    //         '--model' => $modelName,
    //     ]);
    // }

    protected function generateController($modelName)
    {
        $controllerTemplate = "<?php

namespace App\Http\Controllers;

use App\Models\\{$modelName};
use App\Http\Requests\\{$modelName}StoreRequest;
use App\Http\Requests\\{$modelName}UpdateRequest;

class {$modelName}Controller extends Controller
{
    public function index()
    {
        return {$modelName}::all();
    }

    public function store({$modelName}StoreRequest \$request)
    {
        \$model = {$modelName}::create(\$request->validated());
        return response()->json(\$model, 201);
    }

    public function show({$modelName} \$model)
    {
        return response()->json(\$model);
    }

    public function update({$modelName}UpdateRequest \$request, {$modelName} \$model)
    {
        \$model->update(\$request->validated());
        return response()->json(\$model);
    }

    public function destroy({$modelName} \$model)
    {
        \$model->delete();
        return response()->json(null, 204);
    }
}
";

        File::put(app_path("Http/Controllers/{$modelName}Controller.php"), $controllerTemplate);
    }


    protected function generateRequests($modelName)
    {
        $this->call('make:request', ['name' => "{$modelName}StoreRequest"]);
        $this->call('make:request', ['name' => "{$modelName}UpdateRequest"]);
    }

    protected function generateViews($modelName)
    {
        $viewPath = resource_path("views/" . Str::snake(Str::plural($modelName)));
        File::ensureDirectoryExists($viewPath);

        File::put("{$viewPath}/index.blade.php", "<h1>{$modelName} Index</h1>");
        File::put("{$viewPath}/create.blade.php", "<h1>Create {$modelName}</h1>");
        File::put("{$viewPath}/edit.blade.php", "<h1>Edit {$modelName}</h1>");
        File::put("{$viewPath}/show.blade.php", "<h1>Show {$modelName}</h1>");
    }

}
