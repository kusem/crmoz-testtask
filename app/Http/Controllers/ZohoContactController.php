<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZohoContactController extends Controller
{
    private string $accessToken;
    private string $apiDomain;

    function __construct(ZohoAPIController $zohoLogin)
    {
        $this->accessToken = $zohoLogin->getAccessToken();
        $this->apiDomain = $zohoLogin->getApiDomain();
    }

    /**
     * Add contact with optional POST params: company, First_Name, Last_Name, Email, State
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function addContact(Request $request): JsonResponse
    {
        $url = $this->apiDomain. '/crm/v3/Contacts';
        $http = new Client();

        $request->has('company') ?: $request->company = "FOP";
        $request->has('First_Name') ?: $request->First_Name = rand(0, 6) . "Vladik" . rand(0, 10);
        $request->has('Last_Name') ?: $request->Last_Name = rand(0, 6) . "Kuzya" . rand(0, 10);
        $request->has('Email') ?: $request->Email = rand(0, 6) . "kuzya@" . rand(0, 10) . ".ua";
        $request->has('State') ?: $request->State = "Kyiv " . rand(0, 10);

        try {
            $response = $http->post(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $this->accessToken,
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

        $responseJSON = json_decode($response->getBody()->getContents());
        $newUserID = $responseJSON->data[0]->details->id;
        $ownerID = $responseJSON->data[0]->details->Created_By->id;

        $this->saveLastUserToSession($newUserID, $ownerID);

        return response()->json(
            [
                'status' => 1,
                'message' => json_decode($response->getBody()),
                'new_user_id' => $newUserID,
            ]
        );
    }

    private function saveLastUserToSession($lastUserID, $ownerID)
    {
        session(['lastAddedNewUserID' => $lastUserID]);
        session(['ownerID' => $ownerID]);
        session()->save();
    }
}
