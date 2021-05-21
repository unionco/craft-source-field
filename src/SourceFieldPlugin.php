<?php

namespace unionco\sourcefield;

use Craft;
use yii\base\Event;
use craft\base\Plugin;
use craft\services\Fields;
use unionco\sourcefield\fields\SourceField;
use craft\events\RegisterComponentTypesEvent;

class SourceFieldPlugin extends Plugin
{
    /** @var self */
    public static $plugin;

    /** @var string */
    public $schemaVersion = '0.1.0';

    /** @var bool */
    public $hasCpSettings = false;

    /** @inheritdoc */
    public function init()
    {
        parent::init();

        Craft::setAlias('@unionco/source-field', $this->getBasePath());

        static::$plugin = $this;

        $this->controllerNamespace = 'unionco\\sourcefield\\controllers';

        $this->_registerFieldTypes();
        $this->_pluginLoaded();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    private function _registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event): void {
                $event->types[] = SourceField::class;
            }
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    private function _pluginLoaded(): void
    {
        Craft::info(
            /** @psalm-suppress UndefinedClass */
            Craft::t(
                'source-field',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }
}
