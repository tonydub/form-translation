Form Translation Component
==========================

Translate your doctrine objects easily with a custom field for Symfony2 form component.

Requirement
-----------

- Symfony >= v2.3
- Your Doctrine objects configured with an i18n strategy of [GedmoDoctrineExtension](http://github.com/l3pp4rd/DoctrineExtensions).

Installation
------------

To install the form component simply run `composer.phar require "tonydub/form-translation:dev-master"`.

Usage
-----

To use the translatable type you need to register it as a service:

```xml
<service id="acme_demo.form.type.translatable_field" class="Tonydub\Component\Form\Extension\Type\TranslatedFieldType">
        <argument>acme_demo_translatable_field</argument>
        <argument>%acme_demo.translations.locale%</argument>
        <argument>%acme_demo.translations.locales%</argument>
        <tag name="form.type" alias="acme_demo_translatable_field"/>
</service>
```

You can use then use the type with the form builder:

```php
public function buildForm(FormBuilderInterface $builder, array $options)
{
    $builder
        //...
        ->add('title', 'acme_demo_translatable_field', [
                'type' => 'text'
            ])
    ;
}
```

You can find an example of a template fragment twig for translatatble field in file 'Resources/views/Form/div_layout.html.twig'.
