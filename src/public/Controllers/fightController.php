<?php


namespace dawa\controllers;

use dawa\models\Character;
use dawa\models\Element;
use dawa\models\Hero as Hero;
use dawa\models\Monster;
use dawa\models\Race;
use Slim\Slim;

class fightController
{


    public function __construct($container)
    {
        $this->container = $container;
    }

    public function fight($idHero, $idMonster, $groupFight = false)
    {
        $persos = $this->initPersosFromIDs($idHero, $idMonster, $groupFight);
        $leHero = $persos['leHero'];
        $leMonster = $persos['leMonster'];
        $beforeLeHero = $persos['beforeLeHero'];
        $beforeLeMonster = $persos['beforeLeMonster'];
        $fin = false;
        if ($groupFight) {
            $totalAgiliteHero = 0;
            foreach ($leHero as $hero) {
                $totalAgiliteHero += intval($hero['race']['agility']);
            }
            $totalAgiliteMonstre = 0;
            foreach ($leMonster as $monster) {
                $totalAgiliteMonstre += intval($monster['race']['agility']);
            }

            $attaque = ($totalAgiliteHero > $totalAgiliteMonstre) ? $leHero : $leMonster;
            $victime = ($totalAgiliteHero > $totalAgiliteMonstre) ? $leMonster : $leHero;

        } else {
            $attaque = ($leHero['race']['agility'] > $leMonster['race']['agility']) ? $leHero : $leMonster;
            $victime = ($leHero['race']['agility'] > $leMonster['race']['agility']) ? $leMonster : $leHero;
        }
        $tours = [];
        $tour = 1;
        while (!$fin) {
            $resTour = $this->tour($attaque, $victime, $tour, '', $groupFight);
            $fin = $resTour['fin'];
            $attaque = $resTour['attaque'];
            $victime = $resTour['victime'];

            $tours[] = [
                "id" => $tour,
                "log" => $resTour['log']
            ];
            $tour++;
        }
        return ['combat' => [
            "persos" => ['attaque' => $beforeLeHero, 'victime' => $beforeLeMonster],
            "nbTours" => $tour,
            "tours" => $tours,
            "winner" => $attaque,
            "looser" => $victime,
            "fin" => true,
            "type" => (!$groupFight) ? '1v1' : '3v3'
        ]];
    }

    function array_clone_liste($array)
    {
        return array_map(function ($element) {
            return ((is_array($element))
                ? $this->array_clone_liste($element)
                : ((is_object($element))
                    ? clone $element
                    : $element
                )
            );
        }, $array);
    }

    /*
     * Ration DMG/HP
     */
    function findWeakTarget($array)
    {
        $weak = null;
        $weakRatio = 99999;
        foreach ($array as $key => $value) {
            $ratio = $value['race']['attack'] / $value['race']['hp'];
            if ($ratio < $weakRatio && $value['race']['hp'] > 0) {
                $weakRatio = $ratio;
                $weak = $key;
            }
        }
        return $weak;
    }

    function findAttackMan($array)
    {
        $essai = 0;
        $found = false;
        do {
            $essai++;
            $tmp = rand(0, 2);
            $found = $array[$tmp]['race']['hp'] > 0;
            if ($essai > 100) {
                break;
            }
        } while (!$found);
        if (!$found) {
            var_dump('ERREUR SA MERE');
        }
        return $tmp;
    }

    function attaque($att, $vict, $isNewAttaque = false, $groupFight = false)
    {
        if ($groupFight) {
            $attaqueIdx = $this->findAttackMan($att);
            $victimeIdx = $this->findWeakTarget($vict);
            $attaque = $att[$attaqueIdx];
            $victime = $vict[$victimeIdx];
        } else {
            $attaque = $att;
            $victime = $vict;
        }

        $hpLogBefore = $attaque['name'] . ': ' . $attaque['race']['hp'] . 'hp ||| ' . $victime['name'] . ': ' . $victime['race']['hp'] . 'hp';

        $crit = ($attaque['race']['agility'] >= 2 * $victime['race']['agility']);
        $dmg = ($crit) ? $attaque['race']['attack'] * 2 : $attaque['race']['attack'];

//        if (!$isNewAttaque) {
        $isBoostDef = isset($victime['boostDefense']) && $victime['boostDefense'];

        $def = rand(0, 100 - $victime['race']['defense']);

        $dmgDef = 0;
        $tfPercent = (100 - $victime['race']['defense']) * 0.25;
        $fiftyPercent = (100 - $victime['race']['defense']) * 0.50;
        $sfPercent = (100 - $victime['race']['defense']) * 0.75;
        $hundredDef = (100 - $victime['race']['defense']);
        $fullDef = false;
        if (0 < $def && $def <= $tfPercent) {
            $dmgDef = $dmg * 0.10;
        } else {
            if ($tfPercent < $def && $def <= $fiftyPercent) {
                $dmgDef = $dmg * 0.25;
            } else if ($fiftyPercent < $def && $def <= $sfPercent) {
                $dmgDef = $dmg * 0.33;
            } else if ($sfPercent < $def && $def < $hundredDef) {
                $dmgDef = $dmg * 0.50;
            } else if ($def === $hundredDef) {
                $fullDef = true;
                $isBoostDef = false;
                $dmgDef = $dmg;
            }
        }
//        }
        $dmgDef = ($isBoostDef) ? $dmgDef * 1.25 : $dmgDef;

        $dmgLog = $attaque['name'] . ' attaque ' . $victime['name'] . ' pour ' . $dmg;
        $dmgLog .= ($crit) ? ' COUP CRITIQUE !' : '';

//        if (!$isNewAttaque) {
        $defLog = $victime['name'] . ' se défend pour ' . $dmgDef;
        $defLog .= ($fullDef) ? ' DEFENSE PARFAITE ' : '';
//        }
        $victime['race']['hp'] -= ($dmg - $dmgDef);

        $hpLogAfter = $attaque['name'] . ': ' . $attaque['race']['hp'] . 'hp ||| ' . $victime['name'] . ': ' . $victime['race']['hp'] . 'hp';
        if (isset($victime['boostDefense']) && $victime['boostDefense']) {
            $victime['boostDefense'] = false;
        }
        if ($groupFight) {
            $att[$attaqueIdx] = $attaque;
            $vict[$victimeIdx] = $victime;
            $attaque = $att;
            $victime = $vict;
        }
        $log = [
            "type" => "attaque",
            "attaque" => $attaque,
            "hpLogBeforeV" => $hpLogBefore,
            "dmgLogV" => $dmgLog,
            "defLogV" => (isset($defLog)) ? $defLog : '',
            "hpLogAfterV" => $hpLogAfter,
            "dmgDealt" => $dmg - $dmgDef,
            "victime" => $victime
        ];
        return $log;
    }

    function defense($attaque, $victime)
    {
        $hpLogBefore = $attaque['name'] . ': ' . $attaque['race']['hp'] . 'hp ||| ' . $victime['name'] . ': ' . $victime['race']['hp'] . 'hp';

        $logDef = $attaque['name'] . ' boost sa défense pour un tour';
        $attaque['boostDefense'] = true;


        $hpLogAfter = $attaque['name'] . ': ' . $attaque['race']['hp'] . 'hp ||| ' . $victime['name'] . ': ' . $victime['race']['hp'] . 'hp';

        $log = [
            "type" => "defense",
            "attaque" => $attaque,
            "hpLogBeforeV" => $hpLogBefore,
            "defLogV" => $logDef,
            "hpLogAfterV" => $hpLogAfter,
            "dmgDealt" => 0,
            "victime" => $victime
        ];
        return $log;
    }

    public function Index($request, $response)
    {
        $isGroupFight = isset($_GET['type_combat']) && $_GET['type_combat'] === '3v3';
        if (isset($_GET['type_combat'])) {
            $typeCombat = $_GET['type_combat'];
            switch ($typeCombat) {
                case '1v1':
                    $idMonster = $_GET['id_monster'];
                    $idHero = $_GET['id_hero'];
                    break;
                case '3v3':
                    $idMonster = explode(',', $_GET['id_monster']);
                    $idHero = explode(',', $_GET['id_hero']);
                    break;
                default:
                    break;
            }
        } else {
            $idMonster = $_GET['id_monster'];
            $idHero = $_GET['id_hero'];
        }
        $fight = $this->lancerCombat($idHero, $idMonster, $isGroupFight);
        return $this->container->view->render($response, 'fight/fight.html.twig', $fight);
    }

    public function lancerCombat($idHero, $idMonster, $groupFight = false)
    {
        if ($groupFight) {
            $fight = $this->fight($idHero, $idMonster, true);
//            $stats = new StatsController($this->container);
//            $stats->saveFight($idHero, $idMonster, $fight);
            return $fight;
        } else {
            $fight = $this->fight($idHero, $idMonster);
            $stats = new StatsController($this->container);
            $stats->saveFight($idHero, $idMonster, $fight);

            return $fight;
        }
    }


    public function tour($attaque, $victime, $tour, $action = '', $groupFight = false)
    {
        if (!$groupFight) {
            if ($attaque['race']['hp'] > 0 && $victime['race']['hp'] > 0) {
                $fin = false;
                if (isset($action) && $action !== '') {
                    switch ($action) {
                        case 'attaquer':
                            $logTour = $this->attaque($attaque, $victime, true);
                            break;
                        case 'defendre':
                            $logTour = $this->defense($attaque, $victime);
                            break;
                        default:
                            break;
                    }
                } else {
                    $logTour = $this->attaque($attaque, $victime, false);
                }
                $attaque = $logTour['attaque'];
                $victime = $logTour['victime'];
                $attaque['totalDmg'] += $logTour['dmgDealt'];
                $victime['totalDmgTook'] += $logTour['dmgDealt'];
                $log = [
                    'tour' => $tour,
                    'type' => $logTour['type'],
                    'win' => false,
                    'hpLogBefore' => $logTour['hpLogBeforeV'],
                    'dmgLog' => (isset($logTour['dmgLogV'])) ? $logTour['dmgLogV'] : '',
                    'defLog' => $logTour['defLogV'],
                    'hpLogAfter' => $logTour['hpLogAfterV']
                ];
                if ($attaque['race']['hp'] > 0 && $victime['race']['hp'] > 0) {
                    $tmp = $attaque;
                    $attaque = $victime;
                    $victime = $tmp;
                }
            } else {
                $log = [
                    'tour' => 'FIN',
                    'win' => true,
                    'winner' => $attaque,
                    'looser' => $victime
                ];
                $fin = true;
            }
        } else {
            $attaqueAlive = ($attaque[0]['race']['hp'] > 0) || ($attaque[1]['race']['hp'] > 0) || ($attaque[2]['race']['hp'] > 0);
            $victimeAlive = ($victime[0]['race']['hp'] > 0) || ($victime[1]['race']['hp'] > 0) || ($victime[2]['race']['hp'] > 0);

            if ($attaqueAlive && $victimeAlive) {
                $fin = false;
                $logTour = $this->attaque($attaque, $victime, false, true);
                $attaque = $logTour['attaque'];
                $victime = $logTour['victime'];
//                $attaque['totalDmg'] += $logTour['dmgDealt'];
//                $victime['totalDmgTook'] += $logTour['dmgDealt'];
                $log = [
                    'tour' => $tour,
                    'type' => $logTour['type'],
                    'win' => false,
                    'hpLogBefore' => $logTour['hpLogBeforeV'],
                    'dmgLog' => (isset($logTour['dmgLogV'])) ? $logTour['dmgLogV'] : '',
                    'defLog' => $logTour['defLogV'],
                    'hpLogAfter' => $logTour['hpLogAfterV']
                ];
                $attaqueAlive = ($attaque[0]['race']['hp'] > 0) || ($attaque[1]['race']['hp'] > 0) || ($attaque[2]['race']['hp'] > 0);
                $victimeAlive = ($victime[0]['race']['hp'] > 0) || ($victime[1]['race']['hp'] > 0) || ($victime[2]['race']['hp'] > 0);
                if ($attaqueAlive && $victimeAlive) {
                    $tmp = $attaque;
                    $attaque = $victime;
                    $victime = $tmp;
                }
            } else {
                $log = [
                    'tour' => 'FIN',
                    'win' => true,
                    'winner' => $attaque,
                    'looser' => $victime
                ];
                $fin = true;
            }
        }
        return ['fin' => $fin, 'attaque' => $attaque, 'victime' => $victime, 'log' => $log];
    }

    public function initFight($request, $response)
    {
        $idMonster = $_GET['id_monster'];
        $idHero = $_GET['id_hero'];
        $persos = $this->initPersosFromIDs($idHero, $idMonster);

        $leHero = $persos['leHero'];
        $leMonster = $persos['leMonster'];
        $beforeLeHero = $persos['beforeLeHero'];
        $beforeLeMonster = $persos['beforeLeMonster'];

        $persos = ["hero" => $leHero, "monster" => $leMonster];
        $combat = ['combat' => [
            "persos" => $persos
        ]];
        return $this->container->view->render($response, 'fight/initFight.html.twig', $combat);
    }

    public function log($data)
    {
        echo('<div style="color: white">' . $data . '</div>');
    }

    public function startFight($request, $response)
    {
        $persos = json_decode($_POST['persos'], true);

        $attaque = ($persos['hero']['race']['agility'] > $persos['monster']['race']['agility']) ? $persos['hero'] : $persos['monster'];
        $victime = ($persos['hero']['race']['agility'] > $persos['monster']['race']['agility']) ? $persos['monster'] : $persos['hero'];

        $tours = [];
        $tour = 1;
        $log = [];
        $fin = false;
        $resTour = $this->tour($attaque, $victime, $tour, '', false);
        $fin = $resTour['fin'];
        $attaque = $resTour['attaque'];
        $victime = $resTour['victime'];
        $tours[] = [
            "id" => $tour,
            "log" => $resTour['log']
        ];
        $tour++;
//        }
        $combat = ['combat' => [
            "persos" => ['attaque' => $persos['hero'], 'victime' => $persos['monster']],
            "nbTours" => $tour,
            "tours" => $tours,
            "attaque" => $attaque,
            "victime" => $victime,
            "type" => 'tpt'
        ]];
        return $this->container->view->render($response, 'fight/fight.html.twig', $combat);
    }

    function nextTour($request, $response)
    {
        $combat = json_decode($_POST['combat'], true);
        $attaque = $combat['attaque'];
        $victime = $combat['victime'];
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
        }
        $tour = $combat['nbTours'];
        $tours = [];
        $resTour = $this->tour($attaque, $victime, $tour, $action, false);
        $attaque = $resTour['attaque'];
        $victime = $resTour['victime'];
        $tours[] = [
            "id" => $tour,
            "log" => $resTour['log']
        ];
        $tour++;
        $combat = ($resTour['fin']) ?
            ['combat' => [
                "persos" => ['attaque' => $combat['attaque'], 'victime' => $combat['victime']],
                "nbTours" => $tour,
                "tours" => $tours,
                "winner" => $attaque,
                "looser" => $victime,
                "type" => 'tpt',
                "fin" => true
            ]] :
            ['combat' => [
                "persos" => ['attaque' => $combat['attaque'], 'victime' => $combat['victime']],
                "nbTours" => $tour,
                "tours" => $tours,
                "attaque" => $attaque,
                "victime" => $victime,
                "type" => 'tpt',
                "fin" => false
            ]];
        return $this->container->view->render($response, 'fight/fight.html.twig', $combat);
    }

    private function initPersosFromIDs($idHero, $idMonster, $groupFight = false)
    {
        if (!$groupFight) {
            $hero = Hero::where('id_hero', '=', $idHero)->first();
            $characHero = Character::where('id_character', '=', $hero['id_character'])->first();
            $raceHero = Race::where('id_race', '=', $characHero['id_character_race'])->first();
            $elemHero = Element::where('id_element', '=', $hero['id_character_elem'])->first();

            $monster = Monster::where('id_monster', '=', $idMonster)->first();
            $characMonster = Character::where('id_character', '=', $monster['id_character'])->first();
            $raceMonster = Race::where('id_race', '=', $characMonster['id_character_race'])->first();
            $elemMonster = Element::where('id_element', '=', $monster['id_character_elem'])->first();

            $leHero = [
                'perso' => $hero->getAttributes(),
                'char' => $characHero->getAttributes(),
                'race' => $raceHero,
                'elem' => $elemHero,
                'name' => $hero['firstname'] . ' ' . $characHero['name'],
                'totalDmg' => 0.0,
                'totalDmgTook' => 0.0,
                'type' => 'hero'
            ];

            $leMonster = [
                'perso' => $monster->getAttributes(),
                'char' => $characMonster->getAttributes(),
                'race' => $raceMonster,
                'elem' => $elemMonster,
                'name' => $characMonster['name'],
                'totalDmg' => 0.0,
                'totalDmgTook' => 0.0,
                'type' => 'monster'
            ];

            $beforeLeHero = $this->array_clone_liste($leHero);
            $beforeLeMonster = $this->array_clone_liste($leMonster);

            return [
                'leHero' => $leHero,
                'leMonster' => $leMonster,
                'beforeLeHero' => $beforeLeHero,
                'beforeLeMonster' => $beforeLeMonster
            ];
        } else {
            $heroList = [];
            $monsterList = [];

            foreach ($idHero as $lm) {
                $hero = Hero::where('id_hero', '=', $lm)->first();
                $characHero = Character::where('id_character', '=', $hero['id_character'])->first();
                $raceHero = Race::where('id_race', '=', $characHero['id_character_race'])->first();
                $elemHero = Element::where('id_element', '=', $hero['id_character_elem'])->first();

                $tmp = [
                    'perso' => $hero->getAttributes(),
                    'char' => $characHero->getAttributes(),
                    'race' => $raceHero,
                    'elem' => $elemHero,
                    'name' => $hero['firstname'] . ' ' . $characHero['name'],
                    'totalDmg' => 0.0,
                    'totalDmgTook' => 0.0,
                    'type' => 'hero'
                ];

                $heroList[] = $tmp;
            }

            foreach ($idMonster as $lm) {
                $monster = Monster::where('id_monster', '=', $lm)->first();
                $characMonster = Character::where('id_character', '=', $monster['id_character'])->first();
                $raceMonster = Race::where('id_race', '=', $characMonster['id_character_race'])->first();
                $elemMonster = Element::where('id_element', '=', $monster['id_character_elem'])->first();

                $tmp = [
                    'perso' => $monster->getAttributes(),
                    'char' => $characMonster->getAttributes(),
                    'race' => $raceMonster,
                    'elem' => $elemMonster,
                    'name' => $characMonster['name'],
                    'totalDmg' => 0.0,
                    'totalDmgTook' => 0.0,
                    'type' => 'monster'
                ];

                $monsterList[] = $tmp;
            }

            $heroListBefore = [];
            $monsterListBefore = [];

            foreach ($heroList as $hero) {
                $heroListBefore[] = $this->array_clone_liste($hero);
            }

            foreach ($monsterList as $monster) {
                $monsterListBefore[] = $this->array_clone_liste($monster);
            }
            return [
                'leHero' => $heroList,
                'leMonster' => $monsterList,
                'beforeLeHero' => $heroListBefore,
                'beforeLeMonster' => $monsterListBefore
            ];
        }
    }

}
