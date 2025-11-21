<?php

namespace allomambo\CommerceMoneris;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\web\View;
use craft\events\RegisterTemplateRootsEvent;
use yii\base\Event;

/**
 * Moneris Plugin
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0-alpha.1';
    public bool $hasCpSettings = false;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$instance = $this;

        // Register translations
        $this->registerTranslations();

        // Register template roots
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['moneris-gateway'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );

        // Register the gateway type
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = \allomambo\CommerceMoneris\gateways\Moneris::class;
            }
        );
    }

    /**
     * Registers the plugin's translations
     */
    private function registerTranslations(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        Craft::$app->i18n->translations['moneris-gateway'] = [
            'class' => \yii\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => $this->getBasePath() . DIRECTORY_SEPARATOR . 'translations',
        ];
    }
}

