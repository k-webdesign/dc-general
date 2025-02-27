<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2021 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2013-2021 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Contao\Event;

use Contao\Config;
use Contao\StringUtil;
use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Date\ParseDateEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\DataDefinition\Definition\Contao2BackendViewDefinitionInterface;
use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminator;
use ContaoCommunityAlliance\DcGeneral\Contao\Twig\DcGeneralExtension;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\BaseView;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ContaoBackendViewTemplate;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\DecodePropertyValueForWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPanelElementTemplateEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPropertyOptionsEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\ResolveWidgetErrorMessageEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ViewHelpers;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\Properties\PropertyInterface;
use ContaoCommunityAlliance\DcGeneral\DcGeneralEvents;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\DcGeneral\Event\ActionEvent;
use ContaoCommunityAlliance\DcGeneral\Panel\FilterElementInterface;
use ContaoCommunityAlliance\DcGeneral\Panel\LimitElementInterface;
use ContaoCommunityAlliance\DcGeneral\Panel\SearchElementInterface;
use ContaoCommunityAlliance\DcGeneral\Panel\SortElementInterface;
use ContaoCommunityAlliance\DcGeneral\Panel\SubmitElementInterface;
use ContaoCommunityAlliance\DcGeneral\View\Event\RenderReadablePropertyValueEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class Subscriber - gateway to the legacy Contao HOOK style callbacks.
 */
class Subscriber implements EventSubscriberInterface
{
    /**
     * The request mode determinator.
     *
     * @var RequestScopeDeterminator
     */
    private $scopeDeterminator;

    /**
     * ClipboardController constructor.
     *
     * @param RequestScopeDeterminator $scopeDeterminator
     */
    public function __construct(RequestScopeDeterminator $scopeDeterminator)
    {
        $this->scopeDeterminator = $scopeDeterminator;
    }

    /**
     * The config instance.
     *
     * @var \Contao\Config
     */
    private static $config;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            DcGeneralEvents::ACTION                => ['initializePanels', 10],
            GetPanelElementTemplateEvent::NAME     => ['getPanelElementTemplate', -1],
            ResolveWidgetErrorMessageEvent::NAME   => ['resolveWidgetErrorMessage', -1],
            RenderReadablePropertyValueEvent::NAME => 'renderReadablePropertyValue',
            'contao-twig.init'                     => 'initTwig'
        ];
    }

    /**
     * Create a template instance for the default panel elements if none has been created yet.
     *
     * @param GetPanelElementTemplateEvent $event The event.
     *
     * @return void
     */
    public function getPanelElementTemplate(GetPanelElementTemplateEvent $event)
    {
        if (!$this->scopeDeterminator->currentScopeIsBackend()) {
            return;
        }

        if ($event->getTemplate()) {
            return;
        }

        $element = $event->getElement();

        if ($element instanceof FilterElementInterface) {
            $event->setTemplate(new ContaoBackendViewTemplate('dcbe_general_panel_filter'));
        } elseif ($element instanceof LimitElementInterface) {
            $event->setTemplate(new ContaoBackendViewTemplate('dcbe_general_panel_limit'));
        } elseif ($element instanceof SearchElementInterface) {
            $event->setTemplate(new ContaoBackendViewTemplate('dcbe_general_panel_search'));
        } elseif ($element instanceof SortElementInterface) {
            $event->setTemplate(new ContaoBackendViewTemplate('dcbe_general_panel_sort'));
        } elseif ($element instanceof SubmitElementInterface) {
            $event->setTemplate(new ContaoBackendViewTemplate('dcbe_general_panel_submit'));
        }
    }

    /**
     * Resolve a widget error message.
     *
     * @param ResolveWidgetErrorMessageEvent $event The event being processed.
     *
     * @return void
     */
    public function resolveWidgetErrorMessage(ResolveWidgetErrorMessageEvent $event)
    {
        if (!$this->scopeDeterminator->currentScopeIsBackend()) {
            return;
        }

        $error = $event->getError();

        if ($error instanceof \Exception) {
            $event->setError($error->getMessage());
        } elseif (\is_object($error)) {
            if (\method_exists($error, '__toString')) {
                $event->setError((string) $error);
            } else {
                $event->setError(\sprintf('[%s]', \get_class($error)));
            }
        } elseif (!\is_string($error)) {
            $event->setError(\sprintf('[%s]', \gettype($error)));
        }
    }

    /**
     * Fetch the options for a certain property.
     *
     * @param EnvironmentInterface $environment The environment.
     * @param ModelInterface       $model       The model.
     * @param PropertyInterface    $property    The property.
     *
     * @return array
     */
    protected static function getOptions($environment, $model, $property)
    {
        $event = new GetPropertyOptionsEvent($environment, $model);
        $event->setPropertyName($property->getName());
        $event->setOptions($property->getOptions());

        $environment->getEventDispatcher()->dispatch($event, $event::NAME);

        return $event->getOptions();
    }

    /**
     * Decode a value from native data of the data provider to the widget via event.
     *
     * @param EnvironmentInterface $environment The environment.
     * @param ModelInterface       $model       The model.
     * @param string               $property    The property.
     * @param mixed                $value       The value of the property.
     *
     * @return mixed
     */
    private static function decodeValue($environment, $model, $property, $value)
    {
        $event = new DecodePropertyValueForWidgetEvent($environment, $model);
        $event
            ->setProperty($property)
            ->setValue($value);

        $environment->getEventDispatcher()->dispatch($event, \sprintf('%s', $event::NAME));

        return $event->getValue();
    }

    /**
     * Render a timestamp using the given format.
     *
     * @param EventDispatcherInterface $dispatcher The Event dispatcher.
     * @param string                   $dateFormat The date format to use.
     * @param int                      $timeStamp  The timestamp.
     *
     * @return string
     */
    private static function parseDateTime(EventDispatcherInterface $dispatcher, $dateFormat, $timeStamp)
    {
        $dateEvent = new ParseDateEvent($timeStamp, $dateFormat);
        $dispatcher->dispatch($dateEvent, ContaoEvents::DATE_PARSE);

        return $dateEvent->getResult();
    }

    /**
     * Render a property value to readable text.
     *
     * @param RenderReadablePropertyValueEvent $event The event being processed.
     *
     * @return void
     */
    public function renderReadablePropertyValue(RenderReadablePropertyValueEvent $event)
    {
        if (!$this->scopeDeterminator->currentScopeIsBackend()) {
            return;
        }

        if (null !== $event->getRendered()) {
            return;
        }

        $property = $event->getProperty();
        $value    = self::decodeValue(
            $event->getEnvironment(),
            $event->getModel(),
            $event->getProperty()->getName(),
            $event->getValue()
        );

        $extra = $property->getExtra();

        self::renderForeignKeyReadable($event, $extra, $value);
        self::renderArrayReadable($event, $value);
        self::renderTimestampReadable($event, $extra, $value);
        self::renderDateTimePropertyIsTstamp($event, $property, $value);
        self::renderSimpleCheckbox($event, $property, $extra, $value);
        self::renderTextAreaReadable($event, $property, $extra, $value);
        self::renderReferenceReadable($event, $extra, $value);

        if (null !== $event->getRendered()) {
            return;
        }

        self::renderDateTimeValueInstance($event, $value);
        self::renderOptionValueReadable($event, $property, $value);
    }

    /**
     * Add custom twig extension.
     *
     * @param \ContaoTwigInitializeEvent $event The event.
     *
     * @return void
     */
    public function initTwig(\ContaoTwigInitializeEvent $event)
    {
        if (!$this->scopeDeterminator->currentScopeIsBackend()) {
            return;
        }

        $contaoTwig  = $event->getContaoTwig();
        $environment = $contaoTwig->getEnvironment();

        $environment->addExtension(new DcGeneralExtension());
    }

    /**
     * Initialize the panels for known actions so that they always know their state.
     *
     * @param ActionEvent $event The event.
     *
     * @return void
     */
    public function initializePanels(ActionEvent $event)
    {
        if (!$this->scopeDeterminator->currentScopeIsBackend()) {
            return;
        }

        if (!\in_array(
            $event->getAction()->getName(),
            ['copy', 'create', 'paste', 'delete', 'move', 'undo', 'edit', 'toggle', 'showAll', 'show']
        )
        ) {
            return;
        }

        $environment = $event->getEnvironment();
        $definition  = $environment->getDataDefinition();
        $view        = $environment->getView();

        if (!$view instanceof BaseView
            || !$view->getPanel()
            || !$definition->hasDefinition(Contao2BackendViewDefinitionInterface::NAME)
        ) {
            return;
        }

        /** @var Contao2BackendViewDefinitionInterface $backendDefinition */
        $backendDefinition = $definition->getDefinition(Contao2BackendViewDefinitionInterface::NAME);
        $listingConfig     = $backendDefinition->getListingConfig();

        $dataConfig = $environment->getBaseConfigRegistry()->getBaseConfig();
        $panel      = $view->getPanel();

        ViewHelpers::initializeSorting($panel, $dataConfig, $listingConfig);
    }

    /**
     * Set the config instance in use.
     *
     * @param Config $config The config instance.
     *
     * @return void
     */
    public static function setConfig(Config $config)
    {
        self::$config = $config;
    }

    /**
     * Retrieve the config in use.
     *
     * @return Config
     */
    public static function getConfig()
    {
        if (!self::$config) {
            return self::$config = Config::getInstance();
        }

        return self::$config;
    }

    /**
     * Render a foreign key reference.
     *
     * @param RenderReadablePropertyValueEvent $event The event to store the value to.
     * @param array                            $extra The extra data from the property.
     * @param mixed                            $value The value to format.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function renderForeignKeyReadable(RenderReadablePropertyValueEvent $event, $extra, $value)
    {
        if (!isset($extra['foreignKey']) || (null !== $event->getRendered())) {
            return;
        }

        // Not yet impl.
    }

    /**
     * Render an array as readable property value.
     *
     * @param RenderReadablePropertyValueEvent $event The event to store the value to.
     * @param array                            $value The array to render.
     *
     * @return void
     */
    private static function renderArrayReadable(RenderReadablePropertyValueEvent $event, $value)
    {
        if (!\is_array($value) || (null !== $event->getRendered())) {
            return;
        }

        foreach ($value as $kk => $vv) {
            if (\is_array($vv)) {
                $vals       = \array_values($vv);
                $value[$kk] = $vals[0] . ' (' . $vals[1] . ')';
            }
        }

        $event->setRendered(\implode(', ', $value));
    }

    /**
     * Render a timestamp.
     *
     * @param RenderReadablePropertyValueEvent $event The event to store the value to.
     * @param array                            $extra The extra data from the property.
     * @param int                              $value The value to format.
     *
     * @return void
     */
    private static function renderTimestampReadable(RenderReadablePropertyValueEvent $event, $extra, $value)
    {
        if (!isset($extra['rgxp'])
            || !(('date' === $extra['rgxp']) || ('time' === $extra['rgxp']) || ('datim' === $extra['rgxp']))
            || (null !== $event->getRendered())) {
            return;
        }

        $dispatcher = $event->getEnvironment()->getEventDispatcher();

        $event->setRendered(
            self::parseDateTime($dispatcher, self::getConfig()->get($extra['rgxp'] . 'Format'), $value)
        );
    }

    /**
     * Render date time when property is tstamp.
     *
     * @param RenderReadablePropertyValueEvent $event    The event to store the value to.
     * @param PropertyInterface                $property The property for render it.
     * @param int                              $value    The value to format.
     *
     * @return void
     */
    private static function renderDateTimePropertyIsTstamp(RenderReadablePropertyValueEvent $event, $property, $value)
    {
        if ((null !== $event->getRendered()) || ('tstamp' !== $property->getName())) {
            return;
        }

        $dispatcher = $event->getEnvironment()->getEventDispatcher();

        // Date and time format.
        $event->setRendered(self::parseDateTime($dispatcher, self::getConfig()->get('timeFormat'), $value));
    }

    /**
     * Render for simple checkobx
     *
     * @param RenderReadablePropertyValueEvent $event    The event to store the value to.
     * @param PropertyInterface                $property The property for render it.
     * @param array                            $extra    The extra data from the property.
     * @param int                              $value    The value to format.
     *
     * @return void
     */
    private static function renderSimpleCheckbox(RenderReadablePropertyValueEvent $event, $property, $extra, $value)
    {
        if ((null !== $event->getRendered())
            || !(!$extra['multiple'] && ('checkbox' === $property->getWidgetType()))
        ) {
            return;
        }

        $map = [false => 'no', true => 'yes'];

        $event->setRendered($event->getEnvironment()->getTranslator()->translate('MSC.' . $map[(bool) $value]));
    }

    /**
     * Render datetime if the value is instance of datetime .
     *
     * @param RenderReadablePropertyValueEvent $event The event to store the value to.
     * @param int                              $value The value to format.
     *
     * @return void
     */
    private static function renderDateTimeValueInstance($event, $value)
    {
        if (!($value instanceof \DateTime)) {
            return;
        }

        $dispatcher = $event->getEnvironment()->getEventDispatcher();

        $event->setRendered(
            self::parseDateTime($dispatcher, self::getConfig()->get('datimFormat'), $value->getTimestamp())
        );
    }

    /**
     * Render a referenced value.
     *
     * @param RenderReadablePropertyValueEvent $event The event to store the value to.
     * @param array                            $extra The extra data from the property.
     * @param string                           $value The value to format.
     *
     * @return void
     */
    private static function renderReferenceReadable(RenderReadablePropertyValueEvent $event, $extra, $value)
    {
        if (!isset($extra['reference'])
            || !\array_key_exists($value, (array) $extra['reference'])
            || (null !== $event->getRendered())) {
            return;
        }

        if (\is_array($extra['reference'][$value])) {
            $event->setRendered($extra['reference'][$value][0]);

            return;
        }

        $event->setRendered($extra['reference'][$value]);
    }

    /**
     * Render a string if not allow html or preserve tags is given.
     *
     * @param RenderReadablePropertyValueEvent $event    The event to store the value to.
     * @param PropertyInterface                $property The property for render it.
     * @param array                            $extra    The extra data from the property.
     * @param string                           $value    The value to format.
     *
     * @return void
     */
    private static function renderTextAreaReadable(RenderReadablePropertyValueEvent $event, $property, $extra, $value)
    {
        if ((empty($extra['allowHtml']) && empty($extra['preserveTags']))
            || (null !== $event->getRendered())
            || ('textarea' !== $property->getWidgetType())) {
            return;
        }

        $event->setRendered(\nl2br_html5(StringUtil::specialchars($value)));
    }

    /**
     * Render a property option.
     *
     * @param RenderReadablePropertyValueEvent $event    The event to store the value to.
     * @param PropertyInterface                $property The property holding the options.
     * @param mixed                            $value    The value to format.
     *
     * @return void
     */
    private static function renderOptionValueReadable(RenderReadablePropertyValueEvent $event, $property, $value)
    {
        if (!($options = $property->getOptions())) {
            $options = self::getOptions($event->getEnvironment(), $event->getModel(), $event->getProperty());
            if ($options) {
                $property->setOptions($options);
            }
        }

        if (\array_is_assoc($options)) {
            $event->setRendered($options[$value]);
        }
    }
}
