<?php

namespace ZenDesk\Service;

use DateTime;
use EasyDataTableManager\Service\DataTable\BaseDataTable;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use ZenDesk\Utils\ZenDeskManager;
use ZenDesk\ZenDesk;

class ZenDeskTicketsDataTable extends BaseDataTable
{
    protected $request;

    public function __construct(
        protected ZenDeskManager           $manager,
        protected SecurityContext          $securityContext,
        RequestStack             $requestStack,
        EventDispatcherInterface $dispatcher,
        ParserInterface          $parser,
    )
    {
        parent::__construct($requestStack, $dispatcher, $parser);
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @return bool true if retailer has tickets
     */
    public function hasTickets(): bool
    {
        return 0 < $this->manager->getSumTicketsByUser(
                $this->securityContext->getCustomerUser()->getEmail(),
            );
    }

    /**
     * @throws \Exception
     */
    public function buildResponseData($type): Response
    {
        $tickets = $this->manager->getTicketsByUser($this->securityContext->getCustomerUser()->getEmail());

        $sumTickets = $this->manager->getSumTicketsByUser(
            $this->securityContext->getCustomerUser()->getEmail()
        );

        $json = [];

        if ($tickets !== null) {
            $sortedTickets = $this->sortOrder($tickets);

            $json = [
                "draw" => (int)$this->request->get('draw'),
                "recordsTotal" => $sumTickets,
                "recordsFiltered" => $sumTickets,
                "data" => [],
                "tickets" => count($tickets)
            ];
            foreach ($sortedTickets as $ticket)
            {
                $json['data'][] = $this->jsonFormat($ticket);
            }
        }

        return new JsonResponse($json);
    }

    /**
     * @throws \SmartyException
     */
    public function getFiltersTemplate($params = []): string
    {
        return $this->parser->render('datatable/render/zendesk.render.datatable.tickets.js.html', []);
    }

    /**
     * @throws \SmartyException
     */
    public function getRendersTemplate($params = []): string
    {
        return $this->parser->render('datatable/render/zendesk.render.datatable.tickets.js.html', []);
    }

    private function jsonFormat(\stdClass $ticket): array
    {
        $createdAt = new DateTime($ticket->created_at);
        $updateAt = new DateTime($ticket->updated_at);

        if (0 === strcmp(ZenDesk::getConfigValue("zen_desk_ticket_type"), "requested"))
        {
            return
                [
                    $ticket->id,
                    $ticket->subject,
                    $this->manager->getUserById($ticket->assignee_id)->user->name,
                    $createdAt->format('d/m/Y'),
                    $updateAt->format('d/m/Y'),
                    [
                        "status" => $this->translateStatus($ticket->status),
                        "data_status" => $ticket->status
                    ],
                    [
                        'id' => $ticket->id,
                    ]
                ];
        }

        if (0 === strcmp(ZenDesk::getConfigValue("zen_desk_ticket_type"), "assigned"))
        {
            return
                [
                    $ticket->id,
                    $ticket->subject,
                    $this->manager->getUserById($ticket->requester_id)->user->name,
                    $createdAt->format('d/m/Y'),
                    $updateAt->format('d/m/Y'),
                    [
                        "status" => $this->translateStatus($ticket->status),
                        "data_status" => $ticket->status
                    ],
                    [
                        'id' => $ticket->id,
                    ]
                ];
        }

        return
            [
                $ticket->id,
                $ticket->subject,
                $this->manager->getUserById($ticket->requester_id)->user->name,
                $this->manager->getUserById($ticket->assignee_id)->user->name,
                $createdAt->format('d/m/Y'),
                $updateAt->format('d/m/Y'),
                [
                    "status" => $this->translateStatus($ticket->status),
                    "data_status" => $ticket->status
                ],
                [
                    'id' => $ticket->id,
                ]
            ];
    }

    private function sortOrder(array $arrayTickets): array
    {
        $tickets = $this->formatTickets($arrayTickets);

        $columnDefinition = $this->getDefineColumnsDefinition(true)[(int)$this->request->get('order')[0]['column']];
        $sort = (string)$this->request->get('order')[0]['dir'] === 'asc' ? Criteria::ASC : Criteria::DESC;

        if (!(int)$this->request->get('order')[0]['column'])
        {
            //order by update date desc + status asc
            usort($tickets, function($a, $b) use ($columnDefinition)
            {
                $resDate = strcmp($a->updated_at, $b->updated_at);
                $resStatus = strcmp($a->status, $b->status)*10;

                return $resStatus - $resDate;
            });

            return $tickets;
        }

        if (strcmp($sort, "ASC"))
        {
            usort($tickets, function($a, $b) use ($columnDefinition)
            {
                $index = $columnDefinition["name"];
                return -strcmp($a->$index, $b->$index);
            });
        }

        if (strcmp($sort, "DESC"))
        {
            usort($tickets, function($a, $b) use ($columnDefinition)
            {
                $index = $columnDefinition["name"];
                return strcmp($a->$index, $b->$index);
            });
        }

        return $tickets;
    }

    private function formatTickets(array $tickets): array
    {
        $formatted_tickets = [];

        foreach ($tickets as $ticketType)
        {
            foreach ($ticketType as $ticket)
            {
                $formatted_tickets[] = $ticket;
            }
        }

        return $formatted_tickets;
    }

    private function translateStatus(string $status): ?string
    {
        if ($status === "new"){
            return Translator::getInstance()->trans('new', [], ZenDesk::DOMAIN_NAME);
        }
        if ($status === "open"){
            return Translator::getInstance()->trans('open', [], ZenDesk::DOMAIN_NAME);
        }
        if ($status === "pending"){
            return Translator::getInstance()->trans('pending', [], ZenDesk::DOMAIN_NAME);
        }
        if ($status === "solved"){
            return Translator::getInstance()->trans('solved', [], ZenDesk::DOMAIN_NAME);
        }

        return null;
    }

    public function getDefineColumnsDefinition($type): array
    {
        $i = -1;

        $definitions = [];

        $definitions[] =
            [
                'name' => 'id',
                'targets' => ++$i,
                'className' => "text-center id",
                'title' => Translator::getInstance()->trans('ID', [], ZenDesk::DOMAIN_NAME)
            ];

        $definitions[] =
            [
                'name' => 'subject',
                'targets' => ++$i,
                'className' => "text-center subject",
                'title' => Translator::getInstance()->trans('Subject', [], ZenDesk::DOMAIN_NAME)
            ];

        if (0 !== strcmp(ZenDesk::getConfigValue("zen_desk_ticket_type"), "requested"))
        {
            $definitions[] =
                [
                    'name' => 'requester_id',
                    'targets' => ++$i,
                    'className' => "text-center",
                    'title' => Translator::getInstance()->trans('Requester', [], ZenDesk::DOMAIN_NAME),
                ]
            ;
        }

        if (0 !== strcmp(ZenDesk::getConfigValue("zen_desk_ticket_type"), "assigned"))
        {
            $definitions[] =
                [
                    'name' => 'assignee_id',
                    'targets' => ++$i,
                    'className' => "text-center",
                    'title' => Translator::getInstance()->trans('Assignee', [], ZenDesk::DOMAIN_NAME)
                ]
            ;
        }

        $definitions[] =
            [
                'name' => 'created_at',
                'targets' => ++$i,
                'className' => "text-center createdat",
                'title' => Translator::getInstance()->trans('Created At', [], ZenDesk::DOMAIN_NAME),
            ];

        $definitions[] =
            [
                'name' => 'updated_at',
                'targets' => ++$i,
                'className' => "text-center updatedat",
                'title' => Translator::getInstance()->trans('Update At', [], ZenDesk::DOMAIN_NAME),
            ];

        $definitions[] =
            [
                'name' => 'status',
                'targets' => ++$i,
                'className' => "text-center status",
                'render' => "renderStatus",
                'title' => Translator::getInstance()->trans('Status', [], ZenDesk::DOMAIN_NAME),
            ];

        $definitions[] =
            [
                'name' => 'comments',
                'targets' => ++$i,
                'className' => "text-center comments",
                'render' => "renderCommentsFunction",
                'title' => Translator::getInstance()->trans('Actions', [], ZenDesk::DOMAIN_NAME),
            ];

        return $definitions;
    }

    public function handleSearch(ModelCriteria $query): void
    {
        // TODO: Implement handleSearch() method.
    }
}