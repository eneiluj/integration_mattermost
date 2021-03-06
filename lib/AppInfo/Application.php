<?php
/**
 * Nextcloud - Mattermost
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\AppInfo;

use Closure;
use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Mattermost\Listener\CalendarObjectCreatedListener;
use OCA\Mattermost\Listener\CalendarObjectUpdatedListener;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Mattermost\Dashboard\MattermostWidget;
use OCA\Mattermost\Search\MattermostSearchMessagesProvider;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\Mattermost\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_mattermost';

	public const INTEGRATION_USER_AGENT = 'Nextcloud Mattermost integration';

	public const WEBHOOKS_ENABLED_CONFIG_KEY = 'webhooks_enabled';
	public const CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY = 'calendar_event_created_webhook';
	public const CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY = 'calendar_event_updated_webhook';
	public const WEBHOOK_SECRET_CONFIG_KEY = 'webhook_secret';
	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);

		$eventDispatcher = $container->get(IEventDispatcher::class);
		// load files plugin script
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin', 'files');
			Util::addStyle(self::APP_ID, self::APP_ID . '-files');
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(MattermostWidget::class);
		$context->registerSearchProvider(MattermostSearchMessagesProvider::class);

		$context->registerEventListener(CalendarObjectCreatedEvent::class, CalendarObjectCreatedListener::class);
		$context->registerEventListener(CalendarObjectUpdatedEvent::class, CalendarObjectUpdatedListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		Util::addStyle(self::APP_ID, 'mattermost-search');
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();

			if ($this->config->getUserValue($userId, self::APP_ID, 'navigation_enabled', '0') === '1') {
				$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
				$mattermostUrl = $this->config->getUserValue($userId, self::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
				if ($mattermostUrl === '') {
					return;
				}
				$container->get(INavigationManager::class)->add(function () use ($container, $mattermostUrl) {
					$urlGenerator = $container->get(IURLGenerator::class);
					$l10n = $container->get(IL10N::class);
					return [
						'id' => self::APP_ID,
						'order' => 10,
						'href' => $mattermostUrl,
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
						'name' => $l10n->t('Mattermost'),
					];
				});
			}
		}
	}
}

