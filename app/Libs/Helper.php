<?php

namespace App\Libs;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Helper
{
    public static function getToken($authorization = null)
    {
        if ($authorization == null) {
            $authorization = app('request')->header('Authorization');
        }
        if ($authorization && strpos($authorization, 'Bearer ') !== false) {
            $token = str_replace('Bearer ', '', $authorization);
            return $token;
        }
        return false;
    }

    /**
     *
     * @return  object value
     */
    public static function getObjectId($id, $data, $field, $dft = null)
    {

        if (empty($data)) {
            return $dft;
        }
        foreach ($data as $v) {
            if ($v->$field == $id) {
                return $v;
            }
        }
        return $dft;
    }

    /**
     *
     * @return array field id
     */
    public static function getArrId($data, $field)
    {
        if (empty($data)) {
            return [];
        }
        $arr = [];
        foreach ($data as $v) {
            $arr[] = $v->$field;
        }
        return $arr;
    }

    /**
     *
     * @return string or integer
     */
    public static function getId($id, $data, $field, $fieldValue, $dft = null)
    {

        if (empty($data)) {
            return $dft;
        }
        foreach ($data as $v) {
            if ($v->$field == $id) {
                return $v->$fieldValue;
            }
        }
        return $dft;
    }

    /**
     *
     * @return  array object value
     */
    public static function getArray($object, $data, $field, $dft = [])
    {
        if (empty($data)) {
            return $dft;
        }
        $result = [];
        foreach ($data as $v) {
            if ($v->$field == $object->$field) {
                $result[] = $v;
            }
        }
        return $result;
    }

    /**
     * @return array field id
     */
    public static function getArrMultiple($data, array $field, $checkNull = false)
    {
        $result = [];
        foreach ($field as $f) {
            $result[$f] = [];
        }
        if (empty($data)) {
            return $result;
        }
        foreach ($data as $v) {
            foreach ($field as $f) {
                if ($checkNull && $v->$f != null) {
                    $result[$f][] = $v->$f;
                } else {
                    if (!$checkNull) {
                        $result[$f][] = $v->$f;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    /**
     * Get client IP
     * @return string
     */
    public static function getClientIp()
    {
        $ipAddress = '';
        if (self::get('HTTP_CLIENT_IP', 0)) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'] . '';
        } else if (self::get('HTTP_X_FORWARDED_FOR', 0)) {
            // Vinh edit. Để giải quyết trường hợp có 2 IP ex: 10.96.5.2, 10.42.142.104
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') > 0) {
                $addr = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipAddress = trim($addr[0]);
            } else {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] . '';
            }
            // End Vinh edit
        } else if (self::get('HTTP_X_FORWARDED', 0)) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'] . '';
        } else if (self::get('HTTP_FORWARDED_FOR', 0)) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'] . '';
        } else if (self::get('HTTP_FORWARDED', 0)) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'] . '';
        } else if (self::get('REMOTE_ADDR', 0)) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] . '';
        } else {
            $ipAddress = 'unknown ip';
        }
        return $ipAddress;
    }

    public static function getFileToServers($saveDir = '')
    {
        $bucketName = 'pos';
        //Set source content
        $sources = [
            'files',
        ];
        for ($year = 2023; $year <= date('Y'); $year++) {
            $sources[] =  'files/year_' . $year;
        }
        $sources = array_unique($sources);

        $savePaths = array_map(function ($item) use ($saveDir) {
            return !empty($saveDir) ? $item . '/' . $saveDir :  $item . '/' . 'images';
        }, $sources);

        try {
            $urlFiles = [];
            foreach ($savePaths as $savePath) {
                $files = Storage::disk('s3')->allFiles($savePath);

                foreach ($files as $file) {
                    if (empty($file)) {
                        continue;
                    }
                    $urlFiles[] = API_MEDIA . '/' . $bucketName . '/' . $file;
                }
            }

            return $urlFiles;
        } catch (S3Exception $e) {
            Log::warning('Helper getFileToServers S3Exception ', [$e->getMessage()]);
            return false;
        } catch (\Exception $exception) {
            Log::error("Helper getFileToServers fail", [$exception->getMessage()]);
            return [];
        }
        return [];
    }
    public static function uploadFileToServer($file = null, $saveDir = '', $saveFileName = false)
    {

        try {

            $bucketName = 'pos';
            $savePath = !empty($saveDir) ? 'files/year_' . date('Y') . '/' . $saveDir :  'files/year_' . date('Y') . '/images';

            if (empty($file)) {
                return false;
            }
            $fileNameInfo = $file->getClientOriginalName();
            $fileNameInfo = explode('.', $fileNameInfo);
            if (is_array($fileNameInfo) && isset($fileNameInfo[count($fileNameInfo)-1])) {
                unset($fileNameInfo[count($fileNameInfo)-1]);
                $fileNameInfo = implode('.', $fileNameInfo);
            }

            $fileName = $savePath . '/' . $fileNameInfo . '-' . time() . '-' . self::randomString(3). '.' . $file->getClientOriginalExtension();

            if (!$saveFileName) {
                $fileName = $savePath . '/' . time() . '-' . self::randomString(15) . '.' . $file->getClientOriginalExtension();
            }

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'endpoint' => API_MEDIA,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key'    => PUBLIC_MEDIA_KEY,
                    'secret' => SECRET_MEDIA_KEY,
                ],
            ]);

            $res = $s3->putObject([
                'Bucket' => $bucketName,
                'Key'    => $fileName,
                'SourceFile'   => $file->path(),
                'ContentType' => $file->getClientMimeType()
            ]);

            return $bucketName . '/' . $fileName;
        } catch (S3Exception $e) {
            Log::warning('Helper uploadFileToServer S3Exception ', [$e->getMessage()]);
            return false;
        } catch (\Exception $exception) {
            Log::error("Helper uploadFileToServer fail", [$exception->getMessage()]);
            return false;
        }
        return false;
    }

    static function randomString($length = 10, $number = false)
    {
        if ($number) {
            $characters = '0123456789';
        } else {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function sendDataSMSToSouthTelecom($data)
    {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL                 => 'https://adv-api.worldsms.vn/api/v1/sms/campaign/create-dynamic',
            CURLOPT_RETURNTRANSFER      => true,
            CURLOPT_ENCODING            => '',
            CURLOPT_MAXREDIRS           => 10,
            CURLOPT_TIMEOUT             => 300,
            CURLOPT_FOLLOWLOCATION      => true,
            CURLOPT_HTTP_VERSION        => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS          => json_encode($data),
            CURLOPT_CUSTOMREQUEST       => 'POST',
            CURLOPT_HTTPHEADER          => array(
                "accept: application/json",
                "authorization: Basic a2ltb3R0cWNhcGk6dllHT2sxV0lsMVJ1",
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    static function isJSON($string): bool
    {
        try {
            \json_decode($string);
            return \json_last_error() === JSON_ERROR_NONE;
        } catch (\Exception $exception) {
            return false;
        }
    }

    static function getListOrgBranch($permissionCode = null, $currentWorkProfilePositionId = 0)
    {
        try {
            //Get data from request
            $mainPage = app('request')->MainPage ?? '';
            if (empty($permissionCode)) {
                $permissionCode = app('request')->PermissionCode ?? '';
            }
            if (empty($currentWorkProfilePositionId)) {
                $currentWorkProfilePositionId = app('request')->CurrentWorkProfilePositionId ?? 0;
            }
            $header = ['Authorization' => app('request')->header('Authorization') ?? ''];
            $body = [
                'form_params' => [
                    '_widgets' => ['ListOrgBranch'],
                    'CurrentWorkProfilePositionId' => $currentWorkProfilePositionId,
                    'PermissionCode' => $permissionCode,
                    'IP' => self::getClientIp(),
                ]
            ];
            
            //Get data from API
            $remote = Factory::getRemote();
            $remote->curl(API_POS_WIDGET,$body, 'POST', $header);
            $response = $remote->getResponse();

            if ($response && self::isJSON($response)) {
                $response = json_decode($response);
            }
            if (isset($response->widgets)) {
                $response = $response->widgets;
            }
            if ($response && is_array($response)) {
                foreach ($response as $item) {
                    if (isset($item->name) && $item->name == 'ListOrgBranch') {
                        
                        return $item->data ?? [];
                    }
                }
            }
            return [];
        } catch (\Exception $exception) {
            Log::error("Helper getListOrgBranch fail", [$exception->getMessage()]);
            return [];
        }
        return [];
    }
    public static function cleanString($string) {
        $string = str_replace("\t", '', $string);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $string);
        return trim($clean);
    }

    static function formatStaffDisplay($name, $code)
    {
        if (!isset($name) || empty($name)) {
            return '';
        }

        if (!isset($code) || empty($code)) {
            return $name;
        }

        return sprintf('%s (%s)', $name, $code);
    }
}
