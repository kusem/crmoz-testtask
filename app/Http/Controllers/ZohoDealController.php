<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZohoDealController extends Controller
{
    private string $accessToken;
    private string $apiDomain;
    private mixed $lastAddedUser;
    private mixed $lastOwnerID;

    function __construct(ZohoAPIController $zohoLogin)
    {
        $this->accessToken = $zohoLogin->getAccessToken();
        $this->apiDomain = $zohoLogin->getApiDomain();
        $this->lastAddedUser = $zohoLogin->getLastAddedUserID();
        $this->lastOwnerID = $zohoLogin->getLastAddedUserOwnerID();
    }

    /**
     * Add contact with optional POST params: company, First_Name, Last_Name, Email, State
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addDeal(Request $request): JsonResponse
    {
        $url = $this->apiDomain . '/crm/v3/Deals';
        $http = new Client();


        $request->has('Owner') ?: $request->Owner = $this->lastOwnerID;
        $request->has('Description') ?: $request->Description = "You definitely should hire Vlad Kuzmenko so he can grow in your team.";
        $request->has('Contact_Name') ?: $request->Contact_Name = $this->lastAddedUser;
        $request->has('Deal_Name') ?: $request->Deal_Name = "Best project in da life";
        $request->has('Stage') ?: $request->Stage = "Needs Analysis";

        try {
            $response = $http->post(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken '. $this->accessToken,
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
                                    "Stage" => $request->Stage,
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

        return response()->json(
            [
                'status' => 1,
                'message' => json_decode($response->getBody()->getContents()),
            ]
        );
    }
}
