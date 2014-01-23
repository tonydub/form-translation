<?php

namespace Tonydub\Component\Form\Extension\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Tonydub\Component\Form\Extension\EventListener\TranslatedFieldSubscriber;

class TranslatedFieldType extends AbstractType
{

    protected $name;
    protected $locales = [];
    protected $defaultLocale;

    public function __construct($name, $defaultLocale, array $locales)
    {
        $this->name = $name;
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new TranslatedFieldSubscriber($builder->getFormFactory(), [
            'translation_class' => $options['translation_class'],
            'default_locale' => $this->defaultLocale,
            'locales' => $this->locales,
            'type' => $options['type'],
            'remove_empty_translation' => $options['remove_empty_translation'],
            'required_locale' => $options['required_locale'],
            'trim' => $options['trim'],
            'attr' => $options['attr'],
            'max_length' => $options['max_length']
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'translation_class' => null,
            'type' => 'text', // Change this to another widget like 'texarea' if needed
            'property_path'  => 'translations',
            'remove_empty_translation' => true,
            'required_locale' => [],
            'attr' => [
                'class' => $this->getName()
            ],
            'compound' => true
        ]);
    }
}
