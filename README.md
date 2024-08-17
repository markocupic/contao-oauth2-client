<img src="docs/logo.png" width="150" alt="Logo Marko Cupic"/>

# Contao OAuth2 Client
This extension serves as a base plugin for OAuth2 Login Bundles like [contao-github-login](https://github.com/markocupic/contao-github-login) or  [contao-azure-login](https://github.com/markocupic/contao-azure-login) and contains a backend and frontend authenticator.

https://github.com/user-attachments/assets/f86735dc-f535-4908-98e3-b423c192a523

# Follow these steps to create your custom oauth2-login plugin.
* Create a bundle and name it `vendorname/contao-***-login`
* In your composer require 'markocupic/contao-oauth2-client', the OAuth base extension `league/oauth2-github` an e.g. `league/oauth2-client`.
  Have a look at [knpuniversity/oauth2-client-bundle](https://github.com/knpuniversity/oauth2-client-bundle?tab=readme-ov-file#step-1-download-the-client-library)
  to find the client library of your choice.
* Create for your backend and frontend login the `***BackendClientFactory` class (e.g. GoogleBackendClientFactory) and `***FrontendClientFactory` class (e.g. GoogleFrontendClientFactory).
  Both classes have to extend `Markocupic\ContaoOAuth2Client\OAuth2\Client\AbstractClientFactory`.
* As **client name** you should choose something like this: `github_frontend` or `google_backend`. Use only letters and the underscore.
* Create your button generator class `ButtonGenerator` that has to implement `Markocupic\ContaoOAuth2Client\ButtonGenerator\ButtonGeneratorInterface`.
* If the Contao user is not identified by the claim `email`, you have to write your own token handler that has to implement `Markocupic\ContaoOAuth2Client\OAuth2\Token\TokenHandlerInterface`.
* Create your [`Extension`](https://github.com/markocupic/contao-github-login/blob/main/src/DependencyInjection/MarkocupicContaoGitHubLoginExtension.php)
  and [`Configuration`](https://github.com/markocupic/contao-github-login/blob/main/src/DependencyInjection/Configuration.php) class in the `src/DependencyInjection` folder.
* Create your button and store it in the templates/backend directory of your bundle.
* Create the frontend template `mod_login_***.html.twig` that extends `@MarkocupicContaoOAuth2Client/frontend/modules/_mod_login_oauth2_base.html.twig` and store it under `contao\templates\modules\mod_login_***.html.twig`.
* Create the login button component and store it in under `templates\component\_login_button.htl.twig`.
