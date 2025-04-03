<?php

namespace Kizaru\FlatModel;

use Illuminate\Support\Str;

class MakeFlatModel extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:c-model {name} {--table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $model = trim($this->argument('name'), '"\'');
        $entity = $this->getTableName($model);

        try {
            if (empty($model)) {
                throw new \Exception('Model name cannot be empty!');
            }

            if (! defined('APP_PATH')) {
                define('APP_PATH', app_path());
            }

            $this->info('Creating model ...');

            $this->initEntity($entity);
            $res = $this->createModel($model, $entity);
            $this->generateBaseModel();

            $this->info($res);

        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * @param string $model
     * @param string $entity
     * @return string
     * @throws \Exception
     */
    protected function createModel(string $model, string $entity)
    {
        [$modelName, $namespace, $modelPath] = $this->getClassInfo($model, 'App\\Models', APP_PATH . '/Models');

        if (!file_exists($modelPath)) {
            mkdir($modelPath, 0775, true);
        }

        $primaryKey = $this->getPrimaryKey();
        $schema = $this->getSchema();

        $consts = [];
        foreach ($schema as $key) {
            $consts[] = <<<CONTENT
const $key = '$key';
CONTENT;
        }
        $consts = implode("\n\t", $consts);

        if (!empty($primaryKey) && !empty(array_keys($schema, $primaryKey))) {
            unset($schema[array_keys($schema, $primaryKey)[0]]);
        }

        $fillable = implode(",\n\t\t", array_map(function ($field) {
                return '\'' . $field . '\'';
            }, array_diff($schema, array_merge($this->getExcerptColumns(), ['created_at', 'updated_at'])))) . ',';

        $types = $this->getTypes();
        $properties = [];
        foreach ($types as $field => $type) {

            $properties[] = <<<CONTENT
 * @property $type $$field
CONTENT;
        }
        $properties = implode("\n", $properties);

        $modelFile = $modelPath .'/'. $modelName . '.php';
        if (! file_exists($modelFile)) {
            $content = <<<CONTENT
<?php

namespace $namespace;

use App\Models\BaseModel;

/**
 * Class $modelName
 * @package $namespace
 */

/**
 * [auto-gen-property]
$properties
 * [/auto-gen-property]
 *
 */
class $modelName extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string \$table
     */
    protected \$table = '$entity';

    /**
     * The primary key for the model.
     *
     * @var string \$primaryKey
     */
    protected \$primaryKey = '$primaryKey';

    # [auto-gen-attribute]
    $consts
    # [/auto-gen-attribute]

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected \$fillable = [
        # [auto-gen-attribute]
        $fillable
        # [/auto-gen-attribute]
    ];
}
CONTENT;
            // Create model file
            $file = fopen($modelFile, 'w');
            fwrite($file, $content);
            fclose($file);

            $ret = 'Model "\\' . $namespace . '\\' . $modelName . '" was created!';
        }
        else {
            throw new \Exception("$modelFile is existed");
        }

        return $ret;
    }

    protected function generateBaseModel()
    {
        if (! file_exists(APP_PATH.'/Models/BaseModel.php')) {
            $content = <<<CONTENT
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * @return \$this
     */
    public static function createNewInstance(): Model
    {
        return self::query()->make();
    }

    /**
     * @return \$this
     */
    public function cloneAttribute(): Model
    {
        return self::createNewInstance()->fill(\$this->attributesToArray());
    }
}
CONTENT;
            // Create model file
            $file = fopen(APP_PATH.'/Models/BaseModel.php', 'w');
            fwrite($file, $content);
            fclose($file);

            $this->info('BaseModel was created !!!');
        }
    }
}
