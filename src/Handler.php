<?php
namespace yandex\alisa;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use RomaricDrigon\MetaYaml\Loader\YamlLoader;
use Lazer\Classes\Database as DB;
use yandex\alisa\context\ContextManager;

define('LAZER_DATA_PATH', realpath( __DIR__).'/database/');

class Handler {

    /**
     * Файловая система.
     * @var \League\Flysystem\Filesystem
     */
    public $files;

    /**
     * Файл логов
     */
    public $logFile;

    /**
     * ID-навыка.
     * @var string
     */
    public $skillID = "";

    /**
     * Название навыка.
     * @var string
     */
    public $skillName = "";

    /**
     * Токен авторизации навыка.
     * @var string
     */
    public $token = "";

    /**
     * Переменная для обработки Prepare-функции.
     * @var array
     */
    public $vars = [];


    /**
     * Переменные отправленные на payload c текста.
     * @var array
     */
    public $varsPayload = [];

    /**
     * Дириктория с изоброжениями.
     * @var string
     */
    public $imagesDir = "images";

    /**
     * @var ContextManager
     */
    protected $contextManager;

    /**
     * Handler constructor.
     */
    public function __construct($logFile, $imagePath, $blocksPath, $configPath) {
        $this->imagesDir = $imagePath;
        $this->logFile = $logFile;
        $this->files = new Filesystem(new Local($blocksPath), ['visibility' => 'public']);
        $loader = new YamlLoader();
        $setting = $loader->loadFromFile($configPath);
        $this->token     = $setting['skill-token'];
        $this->skillID   = $setting['skill-id'];
        $this->skillName = $setting['skill-name'];
        $this->contextManager = new ContextManager();
    }

    /**
     * Вариация вопросов.
     * @param array  $list
     * @param String $command
     *
     * @return bool
     */
    protected function optionsQuestions(Array $list, $command) {
        foreach ($list as $options) {
            if( $command == $options ) return true;
        }
        return false;
    }

    /**
     * Вариация ответов.
     * @param array $list
     * @param int   $type
     *
     * @return mixed|int
     */
    protected function optionsAnswers(Array $list, $type = 0) {
        if( $type == 0 ) {
            $randomMessage = $list[ rand( 0, count( $list ) - 1 ) ];

            return $randomMessage;
        } else {
            return rand( 0, count( $list ) - 1);
        }
    }

    /**
     * Опциональный выбор Callback (Операция ИЛИ)
     * @param array $list
     * @param array $callback
     *
     * @return bool
     */
    protected function optionsCallback(Array $list, Array $callback) {
        foreach ($list as $options) {
            foreach ($callback as $name=>$value) {
                if( $options == $name ) return true;
            }
        }
        return false;
    }

    /**
     * Подготовленные запросы.
     * @param        $getMessage
     * @param String $command
     *
     * @return bool
     */
    protected function prepare($getMessage, $command) {
        $var = []; $math = "";
        if( !is_array($getMessage) ) {
            $preg= '';
            $varNames = [];
            $words = explode(" ", $getMessage);
            foreach ($words as $key => $value) {
                if (strstr($value, '{') && strstr($value, '}')) {
                    // variable
                    $index = substr(strstr($value, '{'), 1,strpos($value, '}') - 1);
                    $varNames[] = $index;
                    $preg .= '(.*)';
                }else{
                    // keyword
                    $preg .= $value;
                }
            }
            if(preg_match_all('~^' . $preg . '$~mu', $command, $matches, PREG_SET_ORDER)) {
                array_shift($matches[0]);
                foreach ($matches[0] as $key => $match) {
                    $this->vars[$varNames[$key]] = trim($match);
                }
                return true;
            }
        } else {
            foreach ($getMessage as $k=>$msg) {
                $words = explode(" ", $msg);
                $preg = '';
                $varNames = [];
                foreach ($words as $key => $value) {
                    if (strstr($value, '{') && strstr($value, '}')) {
                        // variable
                        $index = substr(strstr($value, '{'), 1,strpos($value, '}') - 1);
                        $varNames[] = $index;
                        $preg .= '(.*)';
                    }else{
                        // keyword
                        $preg .= $value;
                    }
                }
                if(preg_match_all('~^' . $preg . '$~mu', $command, $matches, PREG_SET_ORDER)) {
                    array_shift($matches[0]);
                    foreach ($matches[0] as $key => $match) {
                        $this->vars[$varNames[$key]] = trim($match);
                    }
                    return true;
                } else {
                    continue;
                }
            }
        }
        return false;
    }

    /**
     * Преобразовывает объект в массив.
     * @param $d
     *
     * @return array|Object
     */
    public function objectToArray($d) {
        return json_decode(json_encode(json_decode($d)), true);
    }


    /**
     * Проверка на орфографию и исправление.
     * @param String $message
     *
     * @return mixed|String
     */
    protected function spellingCheck($message) {
        $message = trim($message);
        $ch = curl_init();
        $options = [
            CURLOPT_URL => "https://speller.yandex.net/services/spellservice.json/checkText",
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => "text=".$message."&format=html&lang=ru",
            CURLOPT_RETURNTRANSFER => TRUE
        ];
        curl_setopt_array($ch, $options);
        $r=$this->objectToArray(curl_exec($ch));

        if (curl_errno($ch)) {
            echo curl_error($ch)."\n";
        }
        curl_close($ch);
        foreach ($r as $value) {
            $word = $value['word'];
            $change = $value['s'][0];
            $message = str_replace($word, $change, $message);
        }
        return $message;
    }

    /**
     * Загрузить изображение.
     * @param $path
     *
     * @return mixed
     */
    public function uploadImage($path) {
        $id    = $this->curlImage($this->imagesDir . '/'.  $path)['image']['id'];
        $image = DB::table('images');
        $image->image_name = substr(md5($path), 0, 16);
        $image->image_id = $id;
        $image->file_name = pathinfo($path)['basename'];
        $image->save();
        return $image->find()->asArray();
    }

    /**
     * Curl запрос
     * @param string $path
     *
     * @return array|Object
     */
    public function curlImage($path = "") {
        $ch      = curl_init();
        $options = [
            CURLOPT_URL            => "https://dialogs.yandex.net/api/v1/skills/".$this->skillID."/images",
            CURLOPT_POST           => FALSE,
            CURLOPT_HTTPHEADER         => [
                "Authorization: OAuth {$this->token}",
            ],
            CURLOPT_RETURNTRANSFER => true
        ];

        if( preg_match('/^((https|http)?:\/\/)?([\w\.]+)\.([a-z]{2,6}\.?)(\/[\w\.]*)*\/.*$/', $path) ) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = [
                "url"=>$path
            ];
        } elseif( $path != "" ) {
            array_push($options, "Content-Type: multipart/form-data");
            $options[CURLOPT_POST] = true;
            $file = new \CURLFile(__DIR__ . '../../'.$path);
            $options[CURLOPT_POSTFIELDS] = ["file"=>$file];
        }

        curl_setopt_array( $ch, $options );
        $r = $this->objectToArray(curl_exec( $ch ));
        if ( curl_errno( $ch ) ) {
            echo curl_error( $ch ) . "\n";
        }
        curl_close( $ch );
        return $r;
    }

    /**
     * Файловая система.
     *
     * @param $path
     *
     * @return \League\Flysystem\Filesystem
     */
    public function read($path) {
        return $this->files->read($path);
    }
}
