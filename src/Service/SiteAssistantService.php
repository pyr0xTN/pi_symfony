<?php

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SiteAssistantService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{kind: string, reply: string, action?: array<string, mixed>}
     */
    public function handle(string $message, bool $isAdmin): array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return [
                'kind' => 'refusal',
                'reply' => 'I only answer questions about this website.',
            ];
        }

        $normalized = mb_strtolower($trimmed);

        $action = $this->detectSiteAction($normalized, $isAdmin);
        if ($action !== null) {
            return $action;
        }

        if ($this->isSiteQuestion($normalized)) {
            return [
                'kind' => 'help',
                'reply' => 'I can open services, activities, shop, offers, messenger, posts, dashboard, settings, eye test, log out, change language or theme, enable 2FA, and send the QR code.',
            ];
        }

        return [
            'kind' => 'refusal',
            'reply' => 'I only answer questions about this website.',
        ];
    }

    /**
     * @return array{kind: string, reply: string, action: array<string, mixed>}|null
     */
    private function detectSiteAction(string $message, bool $isAdmin): ?array
    {
        if ($this->hasAny($message, ['dark mode', 'enable dark', 'switch to dark', 'set dark'])) {
            return [
                'kind' => 'action',
                'reply' => 'Switching to dark mode.',
                'action' => ['type' => 'theme', 'value' => 'dark'],
            ];
        }

        if ($this->hasAny($message, ['light mode', 'enable light', 'switch to light', 'set light'])) {
            return [
                'kind' => 'action',
                'reply' => 'Switching to light mode.',
                'action' => ['type' => 'theme', 'value' => 'light'],
            ];
        }

        if ($this->hasAny($message, ['language', 'lang', 'locale', 'french', 'francais', 'english'])) {
            if ($this->hasAny($message, ['french', 'francais', 'fr'])) {
                return [
                    'kind' => 'action',
                    'reply' => 'Changing language to French.',
                    'action' => ['type' => 'language', 'value' => 'fr'],
                ];
            }

            if ($this->hasAny($message, ['english', 'en'])) {
                return [
                    'kind' => 'action',
                    'reply' => 'Changing language to English.',
                    'action' => ['type' => 'language', 'value' => 'en'],
                ];
            }
        }

        if ($this->hasAny($message, ['logout', 'log out', 'sign out', 'exit'])) {
            return $this->navigationResponse('Logging you out.', 'app_logout');
        }

        if ($this->hasAny($message, ['eye test', 'vision test', 'test my vision', 'open eye test'])) {
            return $this->navigationResponse('Opening eye test.', 'app_vision_test');
        }

        if ($this->hasAny($message, ['enable 2fa', 'turn on 2fa', 'activate 2fa', '2fa'])) {
            return [
                'kind' => 'action',
                'reply' => 'Enabling 2FA.',
                'action' => ['type' => 'enable-2fa'],
            ];
        }

        if ($this->hasAny($message, ['send qr', 'qr code', 'send code', 'login qr'])) {
            return [
                'kind' => 'action',
                'reply' => 'Sending your QR code.',
                'action' => ['type' => 'send-qr'],
            ];
        }

        $isNavigationIntent = $this->hasAny($message, ['open', 'go to', 'goto', 'navigate', 'take me to']);
        if ($isNavigationIntent || $this->hasAny($message, ['dashboard', 'settings', 'services', 'activities', 'shop', 'offers', 'messenger', 'posts', 'main page', 'home'])) {
            if ($this->hasAny($message, ['settings'])) {
                return $this->navigationResponse('Opening settings.', 'app_settings');
            }

            if ($this->hasAny($message, ['services'])) {
                return $this->navigationResponse('Opening services.', 'app_services');
            }

            if ($this->hasAny($message, ['activities'])) {
                return $this->mainPageModuleResponse('Opening activities.', 'activities');
            }

            if ($this->hasAny($message, ['shop'])) {
                return $this->navigationResponse('Opening shop.', 'app_shop');
            }

            if ($this->hasAny($message, ['offer', 'offers'])) {
                return $this->navigationResponse('Opening offers.', 'app_offer_index');
            }

            if ($this->hasAny($message, ['messenger'])) {
                return $this->mainPageModuleResponse('Opening messenger.', 'messenger');
            }

            if ($this->hasAny($message, ['posts', 'post'])) {
                return $this->navigationResponse('Opening posts.', 'app_feed');
            }

            if ($this->hasAny($message, ['dashboard'])) {
                if ($isAdmin) {
                    return $this->navigationResponse('Opening dashboard.', 'app_dashboard');
                }

                return $this->navigationResponse('Opening dashboard.', 'app_dashboard');
            }

            if ($this->hasAny($message, ['main page', 'mainpage', 'home'])) {
                return $this->navigationResponse('Opening main page.', 'app_mainpage');
            }
        }

        return null;
    }

    private function isSiteQuestion(string $message): bool
    {
        return $this->hasAny($message, [
            'site', 'website', 'page', 'pages', 'menu', 'service', 'services', 'activity', 'activities',
            'shop', 'offers', 'offer', 'messenger', 'messages', 'posts', 'post', 'dashboard', 'settings',
            'theme', 'language', 'logout', 'log out', 'eye test', 'vision test', '2fa', 'qr', 'help',
            'what can you do', 'can you do', 'how do i', 'open', 'go to', 'navigate', 'show me',
        ]);
    }

    /**
     * @param list<string> $needles
     */
    private function hasAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{kind: string, reply: string, action: array<string, mixed>}
     */
    private function navigationResponse(string $reply, string $routeName): array
    {
        return [
            'kind' => 'action',
            'reply' => $reply,
            'action' => [
                'type' => 'navigate',
                'url' => $this->urlGenerator->generate($routeName),
            ],
        ];
    }

    /**
     * @return array{kind: string, reply: string, action: array<string, mixed>}
     */
    private function mainPageModuleResponse(string $reply, string $module): array
    {
        return [
            'kind' => 'action',
            'reply' => $reply,
            'action' => [
                'type' => 'navigate',
                'url' => $this->urlGenerator->generate('app_mainpage', ['module' => $module]),
            ],
        ];
    }
}
