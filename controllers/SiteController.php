<?php

namespace app\controllers;

use app\components\controllers\WebController;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends WebController
{
    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    public function actionIndex(): Response
    {
        return $this->render('index');
    }
}
