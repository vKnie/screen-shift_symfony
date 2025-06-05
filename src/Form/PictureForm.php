<?php

namespace App\Form;

use App\Entity\Picture;
use App\Entity\Screen;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PictureForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('delay')
            ->add('startDate')
            ->add('endDate')
            ->add('backgroundColor')
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'label' => 'Image (JPEG, PNG...)',
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => false,
            ])
            ->add('screenPicture', EntityType::class, [
                'class' => Screen::class,
                'choice_label' => 'name',
                'label' => 'Écran',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Picture::class,
        ]);
    }
}
