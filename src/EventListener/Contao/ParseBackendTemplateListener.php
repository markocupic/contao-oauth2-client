<?php

declare(strict_types=1);

/*
 * This file is part of Contao OAuth2 Client.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-oauth2-client
 */

namespace Markocupic\ContaoOAuth2Client\EventListener\Contao;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Markocupic\ContaoOAuth2Client\ButtonGenerator\ButtonGeneratorManager;
use Markocupic\ContaoOAuth2Client\Controller\OAuth2StartController;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook('parseBackendTemplate')]
class ParseBackendTemplateListener
{
    public function __construct(
        private readonly ButtonGeneratorManager $buttonGeneratorManager,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly RouterInterface $router,
        private readonly Twig $twig,
        private readonly UriSigner $uriSigner,
        #[Autowire('%markocupic_contao_oauth2_client.disable_contao_core_backend_login%')]
        private readonly bool $disableContaoBackendLogin,
        #[Autowire('%markocupic_contao_oauth2_client.enable_csrf_token_check%')]
        private readonly bool $enableCsrfTokenCheck,
        private readonly InsertTagParser $insertTagParser,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(string $strContent, string $strTemplate): string
    {
        if ('be_login' !== $strTemplate) {
            return $strContent;
        }

        $template = [];
        $arrButtons = [];

        // Get the request token (disabled by default)
        $template['request_token'] = '';
        $template['enable_csrf_token_check'] = false;

        if ($this->enableCsrfTokenCheck) {
            $template['request_token'] = $this->csrfTokenManager->getDefaultTokenValue();
            $template['enable_csrf_token_check'] = true;
        }

        $template['target_path'] = $this->getTargetPath($strContent);
        $template['always_use_target_path'] = $this->getAlwaysUseTargetPath($strContent);

        // Count available & activated buttons
        $countButtons = $this->countAvailableAndEnabledClients();

        // Remove Contao Core backend login form markup if configured, and we have at least one button
        $blnDisableContaoCoreBackendLoginForm = $countButtons && $this->disableContaoBackendLogin;

        $i = 0;

        foreach ($this->clientFactoryManager->getAvailableClientsByFirewallName('contao_backend', true) as $clientFactory) {
            ++$i;

            $clientName = $clientFactory->getName();

            // Generate a signed url to the start route
            $template['url'] = $this->uriSigner->sign($this->router->generate(OAuth2StartController::LOGIN_ROUTE_BACKEND, ['_oauth2_client' => $clientName], UrlGeneratorInterface::ABSOLUTE_URL));
            $template['show_frontend_link'] = $countButtons === $i && $blnDisableContaoCoreBackendLoginForm;

            // Render the button template
            $template['login_button'] = $this->buttonGeneratorManager->getButtonGeneratorForClient($clientName)->renderButton($clientName);

            // Render the form template
            $arrButtons[] = $this->twig->render('@MarkocupicContaoOAuth2Client/backend/component/_login_form.html.twig', $template);
        }

        $hasOauthLogin = (bool) $countButtons;

        // Render the oauth button container template
        $strContainer = $this->twig->render(
            '@MarkocupicContaoOAuth2Client/backend/oauth_login_container.html.twig',
            [
                'has_oauth_login' => $hasOauthLogin,
                'login_forms' => implode('', $arrButtons),
                // The Contao Core backend login will not be hidden, if there is no OAuth2 login available
                'disable_contao_core_backend_login' => $blnDisableContaoCoreBackendLoginForm && $hasOauthLogin,
            ]
        );

        // Replace insert tags
        $strContainer = $this->insertTagParser->replaceInline($strContainer);

        // Inject buttons in front of the Contao Core login form
        $strContent = str_replace('<form', $strContainer.'<form', $strContent);

        // If configured, remove the Contao Core login form
        if ($blnDisableContaoCoreBackendLoginForm) {
            $strContent = $this->removeContaoLoginForm($strContent);
        }

        // Add hack: Test, if input field with id="username" exists.
        return str_replace("$('username').focus();", "if ($('username')){ \n\t\t$('username').focus();\n\t  }", $strContent);
    }

    private function getTargetPath(string $strContent): string
    {
        $targetPath = '';

        if (preg_match('/name="_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
            $targetPath = $matches[1];
        }

        return $targetPath;
    }

    private function getAlwaysUseTargetPath(string $strContent): string
    {
        $targetPath = '';

        if (preg_match('/name="_always_use_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
            $targetPath = $matches[1];
        }

        return $targetPath;
    }

    private function removeContaoLoginForm(string $strContent): string
    {
        return preg_replace('/<form class="tl_login_form"[^>]*>(.*?)<\/form>/is', '', $strContent);
    }

    /**
     * @throws \Exception
     */
    private function countAvailableAndEnabledClients(): int
    {
        return \count($this->clientFactoryManager->getAvailableClientsByFirewallName('contao_backend', true));
    }
}
