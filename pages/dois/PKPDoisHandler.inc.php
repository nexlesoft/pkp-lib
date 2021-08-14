<?php

/**
 * @file /pages/dois/PKPDoisHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoisHandler
 * @ingroup pages_doi
 *
 * @brief Handle requests for DOI management functions.
 */

use APP\components\forms\context\DoiSetupSettingsForm;
use APP\handler\Handler;
use PKP\components\forms\context\PKPDoiRegistrationSettingsForm;
use PKP\components\forms\context\PKPDoiSetupSettingsForm;
use PKP\facades\Locale;
use PKP\plugins\IPKPDoiRegistrationAgency;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

abstract class PKPDoisHandler extends Handler
{
    public $_isBackendPage = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['index', 'management']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new \PKP\security\authorization\ContextRequiredPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Displays the DOI management page
     *
     * @param array $args
     * @param \PKP\handler\PKPRequest $request
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);

        $context = $request->getContext();

        $enabledDoiTypes = $context->getData(Context::SETTING_ENABLED_DOI_TYPES);

        $templateMgr = TemplateManager::getManager($request);

        $commonArgs = [
            'doiPrefix' => $context->getData(Context::SETTING_DOI_PREFIX),
            'doiApiUrl' => $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'dois'),
            'lazyLoad' => true,
            'enabledDoiTypes' => $enabledDoiTypes,
            'registrationAgencyInfo' => $this->_getRegistrationAgencyInfo($context),
        ];

        HookRegistry::call('DoisHandler::setListPanelArgs', [&$commonArgs]);

        $stateComponents = $this->getAppStateComponents($request, $enabledDoiTypes, $commonArgs);

        // DOI settings
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        $contextApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = Locale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $doiSetupSettingsForm = new DoiSetupSettingsForm($contextApiUrl, $locales, $context);
        $doiRegistrationSettingsForm = new PKPDoiRegistrationSettingsForm($contextApiUrl, $locales, $context);
        $stateComponents[PKPDoiSetupSettingsForm::FORM_DOI_SETUP_SETTINGS] = $doiSetupSettingsForm->getConfig();
        $stateComponents[PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS] = $doiRegistrationSettingsForm->getConfig();

        $templateMgr->setState(['components' => $stateComponents]);

        $templateMgr->assign($this->getTemplateVariables($enabledDoiTypes));

        $templateMgr->display('management/dois.tpl');
    }

    /**
     * Set app-specific state components to appear on DOI management page
     */
    abstract protected function getAppStateComponents(\APP\core\Request $request, array $enabledDoiTypes, array $commonArgs): array;

    /**
     * Set Smarty template variables. Which tabs to display are set by the APP.
     */
    protected function getTemplateVariables(array $enabledDoiTypes): array
    {
        return [
            'pageTitle' => __('doi.manager.displayName'),
            'pageComponent' => 'DoiPage',
        ];
    }


    protected function _getRegistrationAgencyInfo(\PKP\context\Context $context): stdClass
    {
        $info = new stdClass();
        $info->isConfigured = false;
        $info->displayName = '';
        $info->errorMessageKey = null;
        $info->registeredMessageKey = null;
        $info->errorMessagePreamble = null;
        $info->registeredMessagePreamble = null;

        /** @var IPKPDoiRegistrationAgency $plugin */
        $plugin = $context->getConfiguredDoiAgency();
        if ($plugin != null) {
            $info->isConfigured = $plugin->isPluginConfigured($context);
            $info->displayName = $plugin->getRegistrationAgencyName();
            $info->errorMessageKey = $plugin->getErrorMessageKey();
            $info->registeredMessageKey = $plugin->getRegisteredMessageKey();
            $info->errorMessagePreamble = __('manager.dois.registrationAgency.errorMessagePreamble', ['registrationAgency' => $plugin->getRegistrationAgencyName()]);
            $info->registeredMessagePreamble = __('manager.dois.registrationAgency.registeredMessagePreamble', ['registrationAgency' => $plugin->getRegistrationAgencyName()]);
        }

        return $info;
    }
}
