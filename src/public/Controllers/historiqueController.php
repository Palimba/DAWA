<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 03/11/2019
 * Time: 20:09
 */

namespace dawa\Controllers;


use dawa\models\Fight;
use dawa\models\Hero;
use dawa\models\Monster;
use dawa\models\Pictures;

class historiqueController
{

    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     * methode qui permet d'afficher le classement
     */
    public function Index($request, $response)
    {
        $listeFight = Fight::first()->leftJoin('statsfight', 'statsfight.id_fight', '=', 'fight.id_fight')->distinct()
            ->get();

        $hero = Hero::first();
        $monster = Monster::first();

        if (!$hero) {

            $this->container->flash->addMessage('error', 'Il n y a pas de héros prêt pour le combat');
            return $response->withRedirect($this->container->router->pathFor('creerHero'));


        } else if (!$monster) {

            $this->container->flash->addMessage('error', 'Il n y a pas de monstre prêt pour le combat');
            return $response->withRedirect($this->container->router->pathFor('creerMonster'));

        } else {
            $listeheros = Hero::first()
                ->leftJoin('character', 'character.id_character', '=', 'hero.id_character')
                ->get();
            foreach ($listeheros as $h) {
                $p = Pictures::where("id_picture", "=", $h["picture"])->get();
                $h["path"] = $p[0]["path"];
            }
            $listeMonster = Monster::first()
                ->leftJoin('character', 'character.id_character', '=', 'monster.id_character')
                ->get();
            foreach ($listeMonster as $m) {
                $p = Pictures::where("id_picture", "=", $m["picture"])->get();
                $m["path"] = $p[0]["path"];
            }

            return $this->container->view->render($response, 'fight/fightHistory.html.twig', ['fights' => $listeFight, 'heros' => $listeheros, 'monsters' => $listeMonster]);

        }
    }

}