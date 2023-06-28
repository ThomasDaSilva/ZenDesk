<?php

namespace ZenDesk\Utils;

use Zendesk\API\HttpClient;
use Zendesk\API\HttpClient as ZendeskAPI;
use ZenDesk\ZenDesk;

class ZenDeskManager
{
    public function getUserByEmail(string $mail, HttpClient $client = null): ?\stdClass
    {
        if ($client === null){
            $client = $this->authZendeskAdmin();
        }

        return  $client->users()->search(array("query" => $mail));
    }

    public function getUserById(int $id, HttpClient $client = null): ?\stdClass
    {
        if ($client === null){
            $client = $this->authZendeskAdmin();
        }

        return  $client->users()->find($id);
    }

    public function getTicketsUser(
        string $user,
        int    $page = -1,
        int    $perPage = -1): ?array
    {
        $client = $this->authZendeskAdmin();

        $stdCustomer = $this->getUserByEmail($user, $client);

        if ($stdCustomer->users != null) {
            $customerId = $stdCustomer->users[0]->id;

            $tickets = [];

            if ($page == -1 || $perPage == -1) {
                //get all customer's ticket requested
                $tickets["requests"] = $client->users($customerId)->tickets()->requested(['sort_order' => 'desc'])->tickets;

                //get all customer's ticket ccd
                $tickets["cdd"] = $client->users($customerId)->tickets()->ccd(['sort_order' => 'desc'])->tickets;

                //get all customer's ticket assigned
                $tickets["assign"] = $client->users($customerId)->tickets()->assigned(['sort_order' => 'desc'])->tickets;

                return $tickets;
            }

            //get all customer's ticket with page and limit
            $tickets["requests"] = $client->users($customerId)->tickets()->requested(['per_page' => $perPage, 'page' => $page, 'sort_order' => 'desc']);

            //get all customer's ticket ccd with page and limit
            $tickets["cdd"] = $client->users($customerId)->tickets()->ccd(['per_page' => $perPage, 'page' => $page, 'sort_order' => 'desc'])->tickets;

            //get all customer's ticket assigned with page and limit
            $tickets["assign"] = $client->users($customerId)->tickets()->assigned(['per_page' => $perPage, 'page' => $page, 'sort_order' => 'desc'])->tickets;

            return $tickets;
        }
        return null;
    }

    public function getAllUsers()
    {
        $client = $this->authZendeskAdmin();

        return $client->users()->findAll()->users;
    }

    private function authZendeskAdmin(): ZendeskAPI
    {
        $client = new ZendeskAPI(ZenDesk::getConfigValue("zen_desk_api_subdomain"));
        $client->setAuth('basic',
            [
                'username' => ZenDesk::getConfigValue("zen_desk_api_username"),
                'token' => ZenDesk::getConfigValue("zen_desk_api_token")
            ]
        );

        return $client;
    }

    public function getCommentTicket(int $id): array
    {
        $client = $this->authZendeskAdmin();

        return get_object_vars($client->tickets($id)->comments()->findAll());
    }

    public function getCommentAuthor($author_id): array
    {
        $client = $this->authZendeskAdmin();

        return get_object_vars($client->users()->find($author_id));
    }

    public function getTicket($id): array
    {
        $client = $this->authZendeskAdmin();

        return get_object_vars($client->tickets($id)->find());
    }

    public function createTicket(array $params): void
    {
        $client = $this->authZendeskAdmin();

        $client->tickets()->create($params);
    }

    /**
     * Update is used to update parameters like status
     * or adding a new comment for a ticket
     *
     * @param array $params
     * @param $id
     * @return void
     */
    public function updateTicket(array $params, $id = null): void
    {
        $client = $this->authZendeskAdmin();

        $client->tickets()->update($id, $params);
    }

    public function uploadFile(array $upload): ?\stdClass
    {
        $client = $this->authZendeskAdmin();

        return $client->attachments()->upload($upload);
    }

    public function getAllGroup()
    {
        $client = $this->authZendeskAdmin();

        return $client->groups()->findAll()->groups;
    }

    public function getAllOrganization()
    {
        $client = $this->authZendeskAdmin();

        return $client->organizations()->findAll()->organizations;
    }

    public function getOrganizationId(string $organization) :?int
    {
        $organizations = $this->getAllOrganization();

        foreach ($organizations as $oneOrganization){
            if ($oneOrganization->name === $organization){
                return $oneOrganization->id;
            }
        }

        return null;
    }
}