<?php

namespace maximSivakon\lib;

use Bitrix\Main\Web\{HttpClient, Json};
use maximSivakon\Config;

class VkApi
{

    /**
     * @var int|null $client_id
     * @var float|int|null $version_api
     * @var string|null $client_secret
     * @var string|null $access_token
     * @var string $token
     * @var string|null $ip
     *
     */
    private ?int $client_id = 0;

    private ?float $version_api = 0;

    private ?string $client_secret = '';

    private ?string $access_token = '';

    private ?string $token;

    private ?string $ip;

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    function __construct($getParam = ["CLIENT_ID", "CLIENT_SECRET", "ACCESS_TOKEN", "VERSION_API", "TOKEN", "IP"])
    {

        $this->client_id = $getParam[ 'CLIENT_ID' ];
        $this->client_secret = $getParam[ 'CLIENT_SECRET' ];
        $this->access_token = $getParam[ 'ACCESS_TOKEN' ];
        $this->version_api = $getParam[ 'VERSION_API' ];
        $this->token = $getParam[ 'TOKEN' ];
        $this->ip = $getParam[ 'IP' ];

        if (isset($this->client_id)
            && $this->client_id
            && isset($this->client_secret)
            && $this->client_secret
            && isset($this->version_api)
            && $this->version_api) {
            $status = self::secureCheckToken(null, null);

            if ($status[ 'status' ] === true) {
                /*Список ошибок -> https://dev.vk.com/reference/errors*/
                throw new \Exception('Не удалось проверить ваш ключ для VK API! '
                    .'Error code: '
                    .$status[ 'info' ][ 'error' ][ 'error_code' ]
                    .'. '
                    .$status[ 'info' ][ 'error' ][ 'error_msg' ]);
            }
        } else {
            die('Не указаны в конфигурационном файле данные для использования VK API!');
        }
    }

    /**
     *
     * Проверяем ключ access_token, если нам не удалось его проверить
     * запускаем метод getAccessToken и через этот же метод вызываем метод
     * secureCheckToken передав ему access_token тем самым пытаемся повторно
     * его проверить через повторный запуск secureCheckToken.
     *
     * Метод проверяет, что ключ доступа пользователя (access_token) выдан
     * именно тому приложению, которому выдан переданный сервисный ключ
     * доступа. Подходит для проверки ключа доступа iFrame и
     * Standalone-приложений.
     *
     * Была целенаправленная разработка метода getAlbumGroupImages
     *
     * @param  bool|null  $tryAgain
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     */
    private function secureCheckToken(?bool $tryAgain): array
    {
        $status = [];

        $aCheckToken = [
            'v'             => $this->version_api,
            'client_secret' => $this->client_secret,
            'access_token'  => $this->access_token,
        ];

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);

        $checkToken = $httpClient->get('https://api.vk.com/method/secure.checkToken?'.http_build_query($aCheckToken));
        $checkToken = Json::decode($checkToken);

        // обработка только 15 ошибки
        if ($checkToken[ 'error' ][ 'error_code' ] == 15 && $tryAgain !== false) {
            self::getAccessToken();
        }

        if ($checkToken[ 'success' ] === 1) {
            $status = [
                'status' => true,
            ];
        } else {
            $status = [
                'status' => false,
                'info'   => $checkToken,
            ];
        }

        return $status;
    }

    /**
     *
     * Получаем access_token
     *
     */
    private function getAccessToken()
    {
        $aAccessToken = [
            'v'             => $this->version_api,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'client_credentials',
        ];

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);

        $access_token = $httpClient->get('https://api.vk.com/oauth/access_token?'.http_build_query($aAccessToken));
        $access_token = Json::decode($access_token);

        $this->access_token = $access_token[ "access_token" ];

        self::secureCheckToken(false);
    }

    private function getDataApi()
    {
        return Config::getInstance()->getParam('VK_API');
    }

    /**
     * @param  string|null  $album_id
     *
     * Тип изображений -> https://dev.vk.com/reference/objects/photo-sizes
     *
     * @return array
     */
    public function getAlbumGroupImages(?string $album_id = ''): ?array
    {
        $aAlbumUrls = [];

        $aIds = explode('_', $album_id);
        [$groupId, $albumId] = $aIds;

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);
        $aParams = [
            'access_token' => $this->access_token,
            'v'            => $this->version_api,
            'owner_id'     => '-'.$groupId,
            'album_id'     => $albumId,
            'photo_sizes'  => 1,
            //'count'        => 1000
        ];

        $response = $httpClient->get('https://api.vk.com/method/photos.get?'.http_build_query($aParams));
        $aResult = Json::decode($response);

        if ($aResult[ 'response' ] && count($aResult[ 'response' ][ 'items' ])) {
            foreach ($aResult[ 'response' ][ 'items' ] as $aItem) {
                foreach ($aItem[ 'sizes' ] as $aImage) {
                    /*Оставляем только изображения 1024-1280px по ширине*/
                    if ($aImage[ 'type' ] != 'z') {
                        continue;
                    }

                    $aAlbumUrls[] = $aImage[ 'url' ];
                }
            }
        }

        return $aAlbumUrls;

        sleep(1); /*Ожидание, чтобы не превысить лимиты*/
    }

    function __destruct()
    {
    }

}