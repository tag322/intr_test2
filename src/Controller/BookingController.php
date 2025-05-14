<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use DateTime;
use Symfony\Component\HttpFoundation\Request;

use Psr\Log\LoggerInterface;


final class BookingController extends AbstractController
{
    #[Route('/create_leads', name: 'create_leads')]
    public function createLeadsWithBooking(): JsonResponse
    {
        $httpClient = new Client();

        $i = 0;

        $resultArr = [];

        $date = new DateTime();
        $date->modify('+1 week');

        while ($i < 15) {
            
            $days = $i%5;
            $date->modify("+$days days");
            $date->getTimestamp();

            $resultArr = [];

            $response = $httpClient->post($_ENV['YADRO_API_URL'] . '/crm/lead/create', [
                'query' => [
                    'key' => $_ENV['AMOCRM_API_KEY'],
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json'=> [

                    [
                        'name' => 'тестовая бронь ' . $i,
                        'price' => 100,
                        'crm_user_id' => 8967010,
                        'status' => 55987554,
                        'custom_fields' => [
                            [
                                'id' => 968675,
                                'name' => 'date_reservation',
                                'type_id' => 6,
                                'values' => [
                                    [
                                        'value' => $date,
                                        'account_id' => 9585804
                                    ]
                                ]
                            ]
                            
                        ]
                    ]
                    
                ]
            ]);

            $i++;

            $body = $response->getBody()->getContents();
            $body = json_decode($body, true);
            $resultArr = array_merge($resultArr, $body['result']);

            sleep(0.5);
        }

        return $this->json([
            $resultArr
        ]);

    }

    #[Route('/fetch_leads', name: 'fetch_leads')]
    public function fetchBookingLeads(Request $req, LoggerInterface $logger): JsonResponse 
    {
        $customFieldID = $req->query->get('customFieldID', 968675);
        $statusesToCheck = $req->query->get('statusesToCheck', "55987554-24374824-24374821");

        $statusesToCheck = explode("-", $statusesToCheck);

        $logger->info(json_encode($statusesToCheck));

        $httpClient = new Client();

        $date = new DateTime();
        $date->modify('-1 month');
        $date = $date->getTimestamp();

        $leads = [];
        $count = 50;
        $page = 0;
        while(true) {
            try {
                $response = $httpClient->get($_ENV['YADRO_API_URL'] . '/crm/lead/list', [
                    'query' => [
                        'key' => $_ENV['AMOCRM_API_KEY'],
                        'status' => $statusesToCheck,
                        'count' => $count,
                        'offset' => $count*$page,
                        'ifmodif' => $date
                    ],
                    'headers' => [
                        'Accept' => 'application/json'
                    ],
                ]);
                $body = $response->getBody()->getContents();
                $body = json_decode($body, true);
            
                if($body['count'] === 0) {
                    break;
                } 

                $page++;
                $leads = array_merge($leads, $body['result']);
                sleep(0.5);
            } catch (RequestException $e) {
                if($e->hasResponse()) {
                    $status = $e->getResponse()->getStatusCode();

                    return $this->json([
                        "error" => "Ошибка " . $status
                    ]);
                } else {
                    return $this->json([
                        "error" => "Неизвестная ошибка"
                    ]);
                }
            }
        }

        $bookingListed = [];
        $unavailableDates = [];
        $N = 5;

        foreach ($leads as $lead) {
            foreach ($lead['custom_fields'] as $field) {
                if ($field['id'] == $customFieldID) {
                    $bookingListed[] = substr($field['values'][0]['value'], 0, 10);
                }
            }
        }

        $bookingListed = array_count_values($bookingListed);

        $unavailableDates = array_keys(array_filter($bookingListed, function ($count) use ($N) {
            return $count >= $N;
        }));

        return $this->json(
            $unavailableDates,
        );
    }

}
