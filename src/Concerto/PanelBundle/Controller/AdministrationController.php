<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\FileService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Concerto\PanelBundle\Service\AdministrationService;
use Concerto\TestBundle\Service\TestSessionCountService;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class AdministrationController
{
    private $templating;
    private $service;
    private $sessionCountService;
    private $fileService;

    public function __construct(EngineInterface $templating, AdministrationService $service, TestSessionCountService $sessionCountService, FileService $fileService)
    {
        $this->templating = $templating;
        $this->service = $service;
        $this->sessionCountService = $sessionCountService;
        $this->fileService = $fileService;
    }

    /**
     * @Route("/AdministrationSetting/map", name="AdministrationSetting_map")
     * @return Response
     */
    public function settingsMapAction()
    {
        return $this->templating->renderResponse('ConcertoPanelBundle::collection.json.twig', array(
            'collection' => array(
                "exposed" => $this->service->getExposedSettingsMap(),
                "internal" => $this->service->getInternalSettingsMap()
            )
        ));
    }

    /**
     * @Route("/Administration/Messages/collection", name="Administration_messages_collection")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function messagesCollectionAction()
    {
        return $this->templating->renderResponse('ConcertoPanelBundle::collection.json.twig', array(
            'collection' => $this->service->getMessagesCollection()
        ));
    }

    /**
     * @Route("/AdministrationSetting/map/update", name="AdministrationSetting_map_update", methods={"POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @return Response
     */
    public function updateSettingsMapAction(Request $request)
    {
        $this->service->setSettings(json_decode($request->get("map")), true);
        $result = array("result" => 0);
        $response = new Response(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/SessionCount/{filter}/collection", name="Administration_session_count_collection", defaults={"filter"="{}"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param string $filter
     * @return Response
     */
    public function sessionCountCollectionAction($filter)
    {
        $collection = $this->sessionCountService->getCollection(json_decode($filter, true));
        return $this->templating->renderResponse('ConcertoPanelBundle::collection.json.twig', array(
            'collection' => $collection
        ));
    }

    /**
     * @Route("/AdministrationSetting/SessionCount/clear", name="AdministrationSetting_session_count_clear", methods={"POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function clearSessionCountAction()
    {
        $this->sessionCountService->clear();
        $result = array("result" => 0);
        $response = new Response(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/Messages/{object_ids}/delete", name="Administration_messages_delete")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param string $object_ids
     * @return Response
     */
    public function deleteMessageAction($object_ids)
    {
        $this->service->deleteMessage($object_ids);
        $response = new Response(json_encode(array("result" => 0, "object_ids" => $object_ids)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/Messages/clear", name="Administration_messages_clear")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function clearMessagesAction()
    {
        $this->service->clearMessages();
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/ScheduledTask/collection", name="Administration_tasks_collection")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function tasksCollectionAction()
    {
        return $this->templating->renderResponse('ConcertoPanelBundle::collection.json.twig', array(
            'collection' => $this->service->getTasksCollection()
        ));
    }

    /**
     * @Route("/Administration/ScheduledTask/package_install", name="Administration_tasks_package_install")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @return Response
     */
    public function taskPackageInstallAction(Request $request)
    {
        $install_options = json_decode($request->get("install_options"), true);
        $return = $this->service->schedulePackageInstallTask($out, $install_options, true);
        $response = new Response(json_encode(array("result" => $return, "out" => $out)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/api_clients/collection", name="Administration_api_clients_collection")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function apiClientCollectionAction()
    {
        return $this->templating->renderResponse('ConcertoPanelBundle::collection.json.twig', array(
            'collection' => $this->service->getApiClientsCollection()
        ));
    }

    /**
     * @Route("/Administration/api_clients/{object_ids}/delete", name="Administration_api_clients_delete")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param string $object_ids
     * @return Response
     */
    public function deleteApiClientAction($object_ids)
    {
        $this->service->deleteApiClient($object_ids);
        $response = new Response(json_encode(array("result" => 0, "object_ids" => $object_ids)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/api_clients/clear", name="Administration_api_clients_clear")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function clearApiClientAction()
    {
        $this->service->clearApiClients();
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/api_clients/add", name="Administration_api_clients_add")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function addApiClientAction()
    {
        $this->service->addApiClient();
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/packages/status", name="Administration_packages_status")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function packagesStatusAction()
    {
        $return_var = $this->service->packageStatus($output);
        $response = new Response(json_encode(array("result" => $return_var ? 0 : 1, "output" => $output)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/content/import", name="Administration_content_import")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param Request $request
     * @return Response
     */
    public function importContentAction(Request $request)
    {
        $file = $request->get("file");
        if ($file) {
            $file = realpath($this->fileService->getPrivateUploadDirectory()) . "/" . $file;
        }
        $url = $request->get("url");
        $instructions = $request->get("instructions");
        $returnCode = $this->service->importContent($file ? $file : $url, $instructions, $output);
        $response = new Response(json_encode(array("result" => $returnCode, "output" => $output)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/Administration/content/export/{instructions}", name="Administration_content_export", defaults={"instructions"="[]"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @param string $instructions
     * @return Response
     */
    public function exportContentAction($instructions = "[]")
    {
        $returnCode = $this->service->exportContent($instructions, $zipPath, $output);
        if ($returnCode === 0) {
            $response = new Response(file_get_contents($zipPath));
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment; filename="export.concerto.zip"');
            return $response;
        } else {
            $response = new Response($output, 500);
            return $response;
        }
    }

    /**
     * @Route("/Administration/user", name="Administration_user")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     * @return Response
     */
    public function getAuthUser()
    {
        $user = $this->service->getAuthorizedUser();

        $content = array("user" => null);
        if ($user) {
            $content = array(
                "user" => array(
                    "id" => $user->getId(),
                    "username" => $user->getUsername()
                )
            );
        }
        $response = new Response(json_encode($content));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
