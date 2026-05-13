<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides client dashboard sidebar items with route-based active state.
 * Use route_prefix so the sidebar can highlight the correct item on any client_* or request_* route.
 */
final class ClientSidebarService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Returns sidebar items for the client area. Each item has route_prefix so
     * the template can set active based on app.request.attributes.get('_route').
     *
     * @return array<int, array{label: string, url: string, icon: string, route_prefix: string}>
     */
    public function getItems(Request $request): array
    {
        $currentRoute = $request->attributes->get('_route', '');

        $items = [
            [
                'label' => 'Dashboard',
                'url' => $this->urlGenerator->generate('client_dashboard'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
                'route_prefix' => 'client_dashboard',
            ],
            [
                'label' => 'Services',
                'url' => $this->urlGenerator->generate('request_list'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                'route_prefix' => 'request',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->urlGenerator->generate('client_contracts_list'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'route_prefix' => 'client_contracts',
            ],
            [
                'label' => 'Offers',
                'url' => $this->urlGenerator->generate('client_offers_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
                'route_prefix' => 'client_offers',
            ],
            [
                'label' => 'Categories',
                'url' => $this->urlGenerator->generate('client_categories_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
                'route_prefix' => 'client_categories',
            ],
        ];

        $items[] = [
            'label' => 'Apply as Freelancer',
            'url' => $this->urlGenerator->generate('client_apply_freelancer'),
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>',
            'route_prefix' => 'client_apply',
        ];

        return $items;
    }
}
