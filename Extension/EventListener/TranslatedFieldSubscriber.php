<?php

namespace Tonydub\Component\Form\Extension\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\PropertyAccess\PropertyAccess;

class TranslatedFieldSubscriber implements EventSubscriberInterface
{
    private $factory;
    private $options;
    private $accessor;

    public function __construct(FormFactoryInterface $factory, array $options = array())
    {
        $this->factory = $factory;

        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);

        $this->options = $resolver->resolve($options);

        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::POST_SUBMIT => 'onPostBind',
            FormEvents::SUBMIT => 'onSubmit',
        );
    }

    /**
     * Overrides the default options from the extended type.
     *
     * @param OptionsResolverInterface $resolver The resolver for the options.
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired([
            'default_locale',
            'locales',
        ]);

        $resolver->setDefaults([
            'type' => 'text', // Change this to another widget like 'texarea' if needed
            'remove_empty_translation' => false,
            'required_locale' => [],
            'attr' => [],
            'trim' => true,
            'max_length' => null,
            'translation_class' => null, // Translation class. If this class name is not given, the method getTranslationClass() is call from parent entity
        ]);

        $resolver->setAllowedTypes(array(
            'attr' => 'array',
        ));
    }

    /**
     * Builds the custom form based on the provided locales
     *
     * @param FormEvent $event
     */
    public function onPreSetData(FormEvent $event)
    {
        $translations = $event->getData();
        $form = $event->getForm();
        $entity = $form->getParent()->getData();
        $fieldTranslated = $form->getName();

        foreach ($this->getTranslationsByField($entity, $fieldTranslated, $translations) as $binded) {

            $content = $binded['locale'] == $this->options['default_locale'] && null !== $entity ?
                $this->accessor->getValue($entity, $fieldTranslated) :
                $binded['translation']->getContent()
            ;

            $options = [
                'label' => $binded['locale'],
                'required' => in_array($binded['locale'], $this->options['required_locale']),
                'mapped'=> false,
                'auto_initialize' => false,
                'max_length' => $this->options['max_length'],
                'attr' => $this->options['attr']
            ];

            $form->add(
                $this->factory->createNamed(
                    $binded['fieldName'], // name
                    $this->options['type'], // type
                    $content, // data
                    $options
                )
            );
        }
    }

    /**
     * if the form passed the validation then set the corresponding Translations
     *
     * @param FormEvent $event
     */
    public function onPostBind(FormEvent $event)
    {
        $form = $event->getForm();
        $translations = $form->getData();
        if (is_array($translations)) {
            $translations = new ArrayCollection($translations);
        }
        $entity = $form->getParent()->getData();
        $fieldTranslated = $form->getName();

        foreach ($this->getTranslationsByField($entity, $fieldTranslated, $translations) as $binded) {
            $content = $form->get($binded['fieldName'])->getData();

            $translation = $binded['translation'];

            if ($binded['locale'] == $this->options['default_locale']) {

                $this->accessor->setValue($entity, $fieldTranslated, $content);

                $translations->removeElement($translation);

                continue;
            }

           // Set the submitted content
           $translation->setContent($content);

           // Test if its new
           if ($translation->getId()) {
                // Delete the translation if its empty
                if(
                    empty($content)
                    &&
                    $this->options['remove_empty_translation']
                )
                {
                    $translations->removeElement($translation);
                }
            } elseif (!empty($content)) {
                //add it to entity
                $entity->addTranslation($translation);

                if (! $translations->contains($translation)) {
                    $translations->add($translation);
                }
            }
        }
    }

    public function onSubmit(FormEvent $event)
    {
        //Validates the submitted form
        $form = $event->getForm();
        $entity = $form->getParent()->getData();
        $fieldTranslated = $form->getName();

        foreach ($this->getLocaleAndFieldNames($fieldTranslated) as $locale => $name) {
            $content = $form->get($name)->getData();

            if(
                empty($content)
                &&
                in_array($locale, $this->options['required_locale']))
            {
                $form->addError(new FormError(sprintf('Field "%s" for locale "%s" cannot be blank', $fieldTranslated, $locale)));
            }
        }
    }

    /**
     * @param  object          $entity
     * @param  string          $field
     * @param  Collection      $data
     * @return ArrayCollection
     */
    private function getTranslationsByField($entity, $field, Collection $data = null)
    {
        //Small helper function to extract all Personal Translation
        //from the Entity for the field we are interested in
        //and combines it with the fields
        $collection = new ArrayCollection;
        $availableTranslations = new ArrayCollection;

        if ($data) {
            foreach ($data as $translation) {
                if ($translation->getField() == $field) {
                    $availableTranslations[$translation->getLocale()] = $translation;
                }
            }
        }

        foreach ($this->getLocaleAndFieldNames($field) as $locale => $name) {

            if (isset($availableTranslations[$locale])) {
                $translation = $availableTranslations[$locale];
            } else {
                $translation = $this->createTranslation($entity, $locale, $field, NULL);
            }

            $collection[] = [
                'locale'      => $locale,
                'fieldName'   => $name,
                'translation' => $translation
            ];
        }

        return $collection;
    }

    /**
     * @param  object                                                                 $entity
     * @param  string                                                                 $locale
     * @param  string                                                                 $fieldTranslated
     * @param  string|null                                                            $content
     * @return Gedmo\Translatable\Entity\MappedSuperclass\AbstractPersonalTranslation
     */
    private function createTranslation($entity, $locale, $fieldTranslated, $content)
    {
        $className = $this->getTranslationClass($entity);

        $translation = new $className();
        $translation
            ->setLocale($locale)
            ->setField($fieldTranslated)
            ->setContent($content)
            ;

        return $translation;
    }

    /**
     * @param  string $name
     * @return array
     */
    private function getLocaleAndFieldNames($prefix)
    {
        // Helper function to generate all field names in format:
        // '<locale>' => '<field>:<locale>'
        $collection = [];

        foreach ($this->options['locales'] as $locale) {
            $collection[$locale] = sprintf('%s:%s', $prefix, $locale);
        }

        return $collection;
    }

    /**
     * Get the translation class to be used
     * for the object $class
     *
     * @return string
     */
    private function getTranslationClass($entity)
    {
        return empty($this->options['translation_class']) ?
            $entity->getTranslationClass()
            :
            $this->options['translation_class']
        ;
    }
}
