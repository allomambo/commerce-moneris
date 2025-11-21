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
    public static ?Plugin $instance = null;
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
                // For plugins, getBasePath() returns the src/ directory
                // We need to go up one level to get the plugin root (where composer.json is)
                $pluginBasePath = $this->getBasePath();
                $pluginRoot = dirname($pluginBasePath);
                $templatePath = $pluginRoot . DIRECTORY_SEPARATOR . 'templates';
                
                // Register the template root if the directory exists
                if (is_dir($templatePath)) {
                    $event->roots['moneris-gateway'] = $templatePath;
                } else {
                    // Fallback: try using dirname from current file
                    $fallbackPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates';
                    if (is_dir($fallbackPath)) {
                        $event->roots['moneris-gateway'] = $fallbackPath;
                    } else {
                        // Log error for debugging
                        Craft::error("Moneris plugin: Template directory not found. Tried: {$templatePath} and {$fallbackPath}", __METHOD__);
                    }
                }
                
                // Ensure Craft's core templates are available
                if (!isset($event->roots['_includes'])) {
                    $event->roots['_includes'] = Craft::getAlias('@craft/templates/_includes');
                }
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

