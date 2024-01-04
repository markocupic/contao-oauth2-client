<img src="docs/logo.png" width="150" alt="Logo Marko Cupic"/>

# Contao OAuth2 Client
This extension serves as a wrapper plugin for OAuth2 plugins like [contao-github-login](https://github.com/markocupic/contao-github-login) and contains a backend and frontend authenticator.

# Follow these steps to create your oauth2 plugin.
* Create a bundle and name it `vendorname/contao-***-login`
* In your composer require 'markocupic/contao-oauth2-client', the OAuth base extension `league/oauth2-github` an e.g. `league/oauth2-client`.
Have a look at [knpuniversity/oauth2-client-bundle](https://github.com/knpuniversity/oauth2-client-bundle?tab=readme-ov-file#step-1-download-the-client-library)
to find the client library of your choice.
* Create for your backend and your frontend authenticators the `***BackendClientFactory` and `***FrontendClientFactory` class.
Both classes have to extend `Markocupic\ContaoOAuth2Client\OAuth2\Client\AbstractClientFactory`.
* As **client name** you should choose something like this: `github_frontend` or `google_backend`. Use only letters and the underscore.
* Create for your button generator class `ButtonGenerator` that have to implement `Markocupic\ContaoOAuth2Client\ButtonGenerator\ButtonGeneratorInterface`
* Create your [`Extension`](https://github.com/markocupic/contao-github-login/blob/main/src/DependencyInjection/MarkocupicContaoGitHubLoginExtension.php)
and [`Configuration`](https://github.com/markocupic/contao-github-login/blob/main/src/DependencyInjection/Configuration.php) class in the `src/DependencyInjection` folder.
* Create your button and store it in the templates/backend directory of your bundle.
* Create the frontend template `mod_login_***`
that extends `@MarkocupicContaoOAuth2Client/frontend/modules/_mod_login_oauth2_base.html.twig` button and store it in the templates/backend directory of your bundle.
