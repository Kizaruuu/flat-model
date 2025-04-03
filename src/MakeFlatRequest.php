<?php

namespace Kizaru\FlatModel;

use Exception;
use Illuminate\Support\Str;

class MakeFlatRequest extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:c-request {name} {--table=}';

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
        $request = trim($this->argument('name'), '"\'');
        $entity = $this->getTableName($request);

        try {
            if (empty($request)) {
                throw new \Exception('Resource name cannot be empty!');
            }

            if (! defined('APP_PATH')) {
                define('APP_PATH', app_path());
            }

            $this->info('Creating resource ...');

            $this->initEntity($entity);
            $res = $this->creatRequest($request);
            $this->generateBaseRequest();

            $this->info($res);

        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create API resource
     * @param string $request
     * @return string
     * @throws Exception
     */
    public function creatRequest(string $request)
    {
        [$requestName, $namespace, $requestPath] = $this->getClassInfo($request, 'App\\Http\\Requests', APP_PATH . '/Http/Requests');
        $requestName .= 'Request';

        if (! file_exists($requestPath)) {
            mkdir($requestPath, 0775, true);
        }

        $primaryKey = $this->getPrimaryKey();
        $schema = $this->getSchema();

        if (!empty($primaryKey) && !empty(array_keys($schema, $primaryKey))) {
            unset($schema[array_keys($schema, $primaryKey)[0]]);
        }

        $types = $this->getTypes();
        $properties = [];
        foreach ($types as $field => $type) {

            $properties[] = <<<CONTENT
 * @property $type $$field
CONTENT;
        }
        $properties = implode("\n", $properties);

        $requestFile = $requestPath .'/'. $requestName . '.php';
        if (! file_exists($requestFile)) {
            $content = <<<CONTENT
<?php

namespace $namespace;

use App\Http\Requests\BaseRequest;

/**
 * [auto-gen-property]
$properties
 * [/auto-gen-property]
 *
 */
class $requestName extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
CONTENT;

            // Create request file
            $file = fopen($requestFile, 'w');
            fwrite($file, $content);
            fclose($file);

            $ret = 'Request "\\' . $namespace . '\\' . $requestName . '" was created!';
        } else {
            throw new Exception("$requestFile is existed");
        }

        return $ret;
    }

    protected function generateBaseRequest()
    {
        if (! file_exists(APP_PATH.'/Http/Requests/BaseRequest.php')) {
            $content = <<<CONTENT
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }
}
CONTENT;
            // Create model file
            $file = fopen(APP_PATH.'/Http/Requests/BaseRequest.php', 'w');
            fwrite($file, $content);
            fclose($file);

            $this->info('BaseRequest was created !!!');
        }
    }
}
