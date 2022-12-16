<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ZohoAPIController extends Controller
{

    /**
     * @var JsonResponse|mixed
     */
    private mixed $accessToken;
    private string $header; //auth header for API

    /**
     * Creates access Token before entering to endpoint.
     */
    function __construct()
    {
        if (session()->missing('zohoRefreshToken')) {
            if (session()->exists('zohoExpiresAt') and Carbon::now()->addHour()->gt(session('zohoExpiresAt'))) {
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
        $this->header = 'Zoho-oauthtoken ' . $this->accessToken;
    }

    /**
     * Refreshing access token
     *
     * @return JsonResponse
     */
    public function refreshAccessToken(): JsonResponse
    {
        $url = 'https://accounts.zoho.com/oauth/v2/token?refresh_token=' . session('zohoRefreshToken') .
            '&client_id=' . env('ZOHO_API_CLIENT_ID') . '&client_secret=' . env('ZOHO_API_CLIENT_SECRET') .
            '&grant_type=refresh_token';
        $response = Http::post($url);
        if ($response->json()['access_token']) {
            session(['zohoAccessToken' => $response->json()['access_token']]);
            session(['zohoExpiresAt' => Carbon::now()->addHour()]);
            session()->save();

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

    /**
     * Add contact with optional POST params: company, First_Name, Last_Name, Email, State
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addContact(Request $request): JsonResponse
    {
        $url = session('zohoApiDomain') . '/crm/v3/Contacts';
        $http = new Client();

        $request->has('company') ?: $request->company = "FOP";
        $request->has('First_Name') ?: $request->First_Name = rand(0, 6) . "Vladik" . rand(0, 10);
        $request->has('Last_Name') ?: $request->Last_Name = rand(0, 6) . "Kuzya" . rand(0, 10);
        $request->has('Email') ?: $request->Email = rand(0, 6) . "kuzya@" . rand(0, 10) . ".ua";
        $request->has('State') ?: $request->State = "Kyiv";

        try {
            $response = $http->post(
                $url,
                [
                    'headers' => [
                        'Authorization' => $this->header,
                    ],
                    'body' => json_encode(
                        [
                            'data' => [
                                [
                                    "Company" => $request->company,
                                    "Last_Name" => $request->Last_Name,
                                    "First_Name" => $request->First_Name,
                                    "Email" => $request->Email,
                                    "State" => $request->State,
                                ],
                            ],
                        ]
                    ),
                ]
            );
        } catch (GuzzleException $e) {
            return response()->json(
                [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'raw_request' => $request->query(),
                ]
            );
        }

        $newUserID = json_decode($response->getBody()->getContents())->data[0]->details->id;
        $ownerID = json_decode($response->getBody()->getContents())->data[0]->details->Created_By->id;
        session(['lastAddedNewUserID' => $newUserID]);
        session(['ownerID' => $ownerID]);
        session()->save();

        return response()->json(
            [
                'status' => 1,
                'message' => json_decode($response->getBody()),
                'new_user_id' => $newUserID,
            ]
        );
    }

    /**
     * Add contact with optional POST params: company, First_Name, Last_Name, Email, State
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addDeal(Request $request): JsonResponse
    {
        $url = session('zohoApiDomain') . '/crm/v3/Deals';
        $http = new Client();

        session()->exists('lastAddedNewUserID') ? $contactUserID = session(
            'lastAddedNewUserID'
        ) : $contactUserID = '5579542000000407271';
        session()->exists('ownerID') ? $ownerID = session(
            'ownerID'
        ) : $ownerID = '5579542000000397001';

        $request->has('Owner') ?: $request->Owner = $ownerID;
        $request->has(
            'Description'
        ) ?: $request->Description = "You definitely should hire Vlad Kuzmenko so he can grow in your team.";
        $request->has('Contact_Name') ?: $request->Contact_Name = $contactUserID;
        $request->has('Deal_Name') ?: $request->Deal_Name = "Best project in da life";
        $request->has('Stage') ?: $request->Stage = "Needs Analysis";

        try {
            $response = $http->post(
                $url,
                [
                    'headers' => [
                        'Authorization' => $this->header,
                    ],
                    'body' => json_encode(
                        [
                            'data' => [
                                [
                                    "Owner" => [
                                        "id" => $request->Owner,
                                    ],
                                    "Description" => $request->Description,
                                    "Contact_Name" => [
                                        'id' => $request->Contact_Name,
                                    ],
                                    "Deal_Name" => $request->Deal_Name,
                                    "Stage" => $request->Deal_Name,
                                ],
                            ],
                        ]
                    ),
                ]
            );
        } catch (GuzzleException $e) {
            return response()->json(
                [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'raw_request' => $request->query(),
                ]
            );
        }

        //session(['lastAddedNewUserID' => json_decode($response->getBody()->getContents())->data[0]->details->id]);

        return response()->json(
            [
                'status' => 1,
                'message' => json_decode($response->getBody()->getContents()),
            ]
        );
    }


}
