<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use App\Entity\ServiceRequirement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ServiceRequirementController extends AbstractController
{
    private function getSidebar(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('client_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Services',
                'url' => $this->generateUrl('request_list'), 
                'active' => $active === 'services',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('client_contracts_list'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Categories',
                'url' => $this->generateUrl('client_categories_index'),
                'active' => $active === 'categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
        ];
    }
    private function validateRequirementData(Request $request): array
    {
        $errors = [];
        $title = trim($request->request->get('title', ''));
        $details = trim($request->request->get('details', ''));
        $priority = (int) $request->request->get('priority_level');
        $type = $request->request->get('requirement_type');
        $format = $request->request->get('answer_format');

        // 1. Title Validation
        if (strlen($title) < 5 || strlen($title) > 100) {
            $errors[] = "The title must be between 5 and 100 characters long.";
        }

        // 2. Details Validation
        if (empty($details) || strlen($details) < 10) {
            $errors[] = "Please provide more details (min 10 characters).";
        }

        // 3. Priority Range (Assuming 1 to 5)
        if ($priority < 1 || $priority > 5) {
            $errors[] = "Priority level must be between 1 and 5.";
        }

        return $errors;
    }

    #[Route('/requests/requirements/{id}', name: 'requirement_manage')]
    public function manage(ServiceRequest $serviceRequest,EntityManagerInterface $em): Response
    {
        if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('You do not have permission to edit this request.');
    }
        $requirements = $em->getRepository(ServiceRequirement::class)->findBy(
        ['service' => $serviceRequest],
        ['priority_level' => 'DESC'] 
    );
        return $this->render('service_requirement/manage.html.twig', [
            'serviceRequest' => $serviceRequest,
            'requirements' => $requirements,
            'sidebar_items' => $this->getSidebar('services'),
        ]);
    }

    #[Route('/requests/{id}/requirements/add', name: 'requirement_add', methods: ['POST'])]
    public function add(ServiceRequest $serviceRequest, Request $request, EntityManagerInterface $em): Response
    {
        if ($serviceRequest->getClient() !== $this->getUser()) {
                throw $this->createAccessDeniedException('This is not your request.');
            }
        $errors = $this->validateRequirementData($request);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('requirement_manage', ['id' => $serviceRequest->getId()]);
        }
        
        $requirement = new ServiceRequirement();
        
        // Standard text fields
        $requirement->setTitle($request->request->get('title'));
        $requirement->setDetails($request->request->get('details'));
        
        // Custom select fields
        $requirement->setRequirementType($request->request->get('requirement_type'));
        $requirement->setAnswerFormat($request->request->get('answer_format')); // PDF, image, etc.
        $requirement->setPriorityLevel((int) $request->request->get('priority_level'));
        
        // Boolean and JSON defaults
        $requirement->setIsMandatory($request->request->has('is_mandatory'));
        $requirement->setOptionsJson([]);
        
        // Relationship
        $requirement->setService($serviceRequest); 

        $em->persist($requirement);
        $em->flush();

        return $this->redirectToRoute('requirement_manage', ['id' => $serviceRequest->getId()]);
    }
    #[Route('/requirements/delete/{id}', name: 'requirement_delete', methods: ['POST'])]
    public function delete(ServiceRequirement $requirement, EntityManagerInterface $em): Response
    {
        if ($requirement->getService()->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You do not have permission to delete this requirement.');
        }
        $serviceRequestId = $requirement->getService()->getId();
        $em->remove($requirement);
        $em->flush();

        return $this->redirectToRoute('requirement_manage', ['id' => $serviceRequestId]);
    }

    #[Route('/requirements/edit/{id}', name: 'requirement_update', methods: ['POST'])]
    public function update(ServiceRequirement $requirement, Request $request, EntityManagerInterface $em): Response
    {
        if ($requirement->getService()->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You do not have permission to edit this requirement.');
        }
        $errors = $this->validateRequirementData($request);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('requirement_manage', ['id' => $requirement->getService()->getId()]);
        }

        $requirement->setTitle($request->request->get('title'));
        $requirement->setDetails($request->request->get('details'));
        $requirement->setRequirementType($request->request->get('requirement_type'));
        $requirement->setAnswerFormat($request->request->get('answer_format'));
        $requirement->setPriorityLevel($request->request->get('priority_level'));
        $requirement->setIsMandatory($request->request->has('is_mandatory'));

        $em->flush();

        return $this->redirectToRoute('requirement_manage', ['id' => $requirement->getService()->getId()]);
    }
    
}