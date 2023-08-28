<?php

namespace Kizaru\FlatModel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class MakeFlatModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:c-model {name} {--table=}';

    /**
     * @var array
     */
    protected $types = [];

    /**
     * @var array
     */
    protected $schema = [];

    protected $primaryKey = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $model = trim($this->argument('name'), '"\'');
        $entity = $this->option('table');

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

    public function initEntity(string $table)
    {
        $data = DB::connection()->getPdo()->prepare("DESCRIBE `{$table}`");
        $data->execute();

        if ($data->rowCount() <= 0) {
            return;
        }

        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $this->schema[] = $row["Field"];
            $this->types[$row["Field"]] = $this->getType($row["Type"]);
            if ($row['Key'] === 'PRI') {
                $this->primaryKey = $row['Field'];
            }
        }
    }

    /**
     * @param string $type
     * @return string|null
     */
    protected function getType(string $type) {
        if (preg_match('/^(bigint|int|integer|smallint|tinyint|bit|numeric)(\(([\d]+)\))?/', $type, $matches)) {
            return 'int';
        }

        if (preg_match('/^(real|decimal|double|float)(\(([\d]+),([\d]+)\))?/', $type, $matches)) {
            return 'float';
        }

        if (preg_match('/^(longtext|text|mediumtext|tinytext|varchar|char|enum|set|varbinary|year|date|datetime|time|timestamp)(\(([\d]+)\))?/', $type, $matches)) {
            return 'string';
        }

        return null;
    }

    protected function createModel($model, $entity)
    {
        $model = array_map('ucfirst', array_filter(preg_split('/[^a-z0-9]/i', $model)));
        $modelName = $this->getClassName(array_pop($model));

        $modelPath = APP_PATH . '/Models';
        $namespace = 'App\\Models';

        if (! empty($model)) {
            $modelPath .= '/' . join('/', $model);
            $namespace .= '\\' . join('\\', $model);
        }

        if (! file_exists($modelPath)) {
            mkdir($modelPath, 0775, true);
        }

        $primaryKey = $this->getPrimaryKey();
        $schema = $this->getSchema();

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
            throw new \Exception("{$modelFile} is existed");
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

    protected function getSchema(): array
    {
        return $this->schema;
    }

    protected function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return array
     */
    protected function getExcerptColumns(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getPrimaryKey(): string
    {
        if ($this->primaryKey) return $this->primaryKey;
        
        if (array_key_exists('id', $this->schema)) return 'id';
        
        return '';
    }

    /**
     * Get class name
     * @param $name
     * @return string
     */
    protected function getClassName($name): string
    {
        $className = array_filter(preg_split('/[^a-z]/i', $name));
        $className = array_map('ucfirst', $className);
        return join('', $className);
    }
}
