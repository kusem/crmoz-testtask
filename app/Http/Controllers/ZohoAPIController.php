<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class ZohoAPIController extends Controller
{

    /**
     * @var JsonResponse|mixed
     */
    private mixed $accessToken;

    /**
     * Creates access Token before entering to endpoint.
     */
    function __construct()
    {
        if (session()->missing('zohoRefreshToken')) {
            if (session()->exists('zohoExpiresAt') and Carbon::now()->addHour()->lt(session('zohoExpiresAt'))) {
                $this->refreshAccessToken();
            } else {
                $loginTry = $this->doZohoLogin();

                if ($loginTry['status'] === 0) {
                    die(json_encode($loginTry));
                }
                $this->accessToken = $loginTry['message'];
            }
        } else {
            $this->accessToken = session()->get('zohoAccessToken');
        }
    }

    public function refreshAccessToken()
    {
        $url = 'https://accounts.zoho.com/oauth/v2/token?refresh_token=' . session('zohoRefreshToken') .
            '&client_id=' . env('ZOHO_API_CLIENT_ID') . '&client_secret=' . env('ZOHO_API_CLIENT_SECRET') .
            '&grant_type=refresh_token';
//dd($url);
        $response = Http::post($url);
        if ($response->json()['access_token']) {
            session(['zohoAccessToken' => $response->json()['access_token']]);
            session(['zohoExpiresAt' => Carbon::now()->addHour()]);

            return response()->json(
                [
                    'status' => 1,
                    'message' => 'Access token updated.',
                    'zohoAccessToken' => $response->json()['access_token'],
                ]
            );
        }

        return response()->json(
            [
                'status' => 0,
                'message' => 'Something went wrong.',
                'zoho_response' => $response->json(),
            ]
        );
    }

    /**
     * Receiving access token from Zoho API
     *
     * @return array
     */
    protected function doZohoLogin(): array
    {
        $url = 'https://accounts.zoho.com/oauth/v2/token';

        $http = new Client();

        try {
            $response = $http->post(
                $url,
                [
                    'form_params' => [
                        'client_id' => env('ZOHO_API_CLIENT_ID'),
                        'client_secret' => env('ZOHO_API_CLIENT_SECRET'),
                        'code' => env('ZOHO_API_CLIENT_TOKEN'),
                        'redirect_uri' => env('APP_URL'),
                        'grant_type' => 'authorization_code',
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            return [
                'status' => 0,
                'message' => $e,
            ];
        }

        $body = json_decode($response->getBody(), true);

        if (isset($body['access_token'])) {
            $this->saveAuthDataToSession($body);

            return [
                'status' => 1,
                'message' => $body['access_token'],
            ];
        }

        return [
            'status' => 0,
            'message' => 'You are not logged in. Kindly update credentials at .env file.',
            'zoho_message' => $body,
        ];
    }

    /**
     * Saves auth data to session - to store it at one place
     *
     * @param $data
     */
    private function saveAuthDataToSession($data)
    {
        session(['zohoRefreshToken' => $data['refresh_token']]);
        session(['zohoApiDomain' => $data['api_domain']]);
        session(['zohoAccessToken' => $data['access_token']]);
        session(['zohoExpiresAt' => Carbon::now()->addHour()]);
        session()->save();
    }

    /**
     * Destroy session - so we've to login again
     *
     * @return JsonResponse
     */
    public function doZohoLogout(): JsonResponse
    {
        session()->flush();

        return response()->json(
            [
                'status' => 1,
                'message' => 'Logged out. Kindly update credentials at .env to use the system again.',
            ]
        );
    }

    public function getContacts()
    {
        $url = session('zohoApiDomain') . '/crm/v3/settings/modules';
        $http = new Client();

        //dd(session()->all());
        //dd($this->accessToken);
        $header = 'Authorization: Zoho-oauthtoken ' . $this->accessToken;
        try {
            $response = $http->get($url, ['headers' => [$header]]);
        } catch (GuzzleException $e) {
            dd($e);
        }
        dd($response);
        $response = Http::dd()->withHeaders([$header])->get($url);

        //print_r($response->body());

        return $response->json();
    }
}
