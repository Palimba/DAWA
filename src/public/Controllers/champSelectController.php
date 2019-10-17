<?php

namespace dawa\controllers;

use dawa\models\Character;
use Slim\Slim;
use dawa\models\Hero as Hero;
use dawa\models\Monster as Monster;

class champSelectController{

    public function __construct($container){
        $this->container = $container;
    }

    public function Index($request, $response){


        $hero = Hero::first();
        $monster = Monster::first();

        if (!$hero) {

            $this->container->flash->addMessage('error', 'Il n y a pas de héros prêt pour le combat');
            return $response->withRedirect($this->container->router->pathFor('creerHero'));


        }else if (!$monster){

            $this->container->flash->addMessage('error', 'Il n y a pas de monstre prêt pour le combat');
            return $response->withRedirect($this->container->router->pathFor('creerMonster'));

        }else{
            $hero = Hero::first()
                ->leftJoin('character','character.id_character', '=', 'hero.id_character')
                ->get();
            $monster = Monster::first()
                ->leftJoin('character','character.id_character', '=', 'monster.id_character')
                ->get();

            $rand[0]=$this->randomChampSelect($hero);
            $rand[1]=$this->randomChampSelect($monster);
            var_dump($rand);
            $this->container->view->render($response, 'championSelect/affichage.html.twig', ['hero' => $hero, 'monster' => $monster, 'rand' => $rand]);

        }


    }

    public function randomChampSelect($carac){

        return random_int(1,count($carac));

    }
}

