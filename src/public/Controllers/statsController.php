<?php

namespace dawa\controllers;

use dawa\models\StatsFight;
use dawa\models\StatsCharac;
use dawa\models\Hero;
use dawa\models\Monster;
use dawa\models\Fight;
use dawa\controllers\fightController;

class statsController
{

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function saveFight($id_hero, $id_monster, $fight){
        $id_combat = Fight::count() + 1;
        Fight::create([
            'id_fight' => $id_combat,
            'id_hero' => $id_hero,
            'id_monster' => $id_monster
        ]);
        
        $this->saveLogs($fight, $id_combat);
    }

    public function saveLogs($fight, $id_combat)
    {
        
//         winner, looser, nbPvWinner, nbDmgWinner, nbDmgLooser
        $winner = $fight['combat']['winner'];
        $looser = $fight['combat']['looser'];
        $winnerDmg = $fight['combat']['winner']['totalDmg'];
        $looserDmg = $fight['combat']['looser']['totalDmg'];
        $winnerDmgTook = $fight['combat']['winner']['totalDmgTook']; 
        $looserDmgTook = $fight['combat']['looser']['totalDmgTook'];

        StatsFight::create([
            'id_fight' => $id_combat,
            'id_character' => $winner['perso']['id_character'],
            'isWinner' => 1,
            'dmgInfliges' => $winnerDmg,
            'dmgRecus' => $winnerDmgTook
        ]);

        StatsFight::create([
            'id_fight' => $id_combat,
            'id_character' => $looser['perso']['id_character'],
            'isWinner' => 0,
            'dmgInfliges' => $looserDmg,
            'dmgRecus' => $looserDmgTook
        ]);

    $this->statsCharac($winner, $looser);

    }

    public function statsCharac($winner, $looser){
        $win = StatsCharac::where('id_charac', '=', $winner['perso']['id_character'])->first();
        $loo = StatsCharac::where('id_charac', '=', $looser['perso']['id_character'])->first();
        if($win !== NULL){
            $win->increment('nbWin');
            $win->increment('nbTotal');
        }else{
            StatsCharac::create([
                'id_charac' => $winner['perso']['id_character'],
                'type' => '1vs1',
                'nbWin' => 1,
                'nbTotal' => 1
            ]);
        }

        if($loo !== NULL){
            $loo->increment('nbLoose');
            $loo->increment('nbTotal');
            
        }else{
            StatsCharac::create([
                'id_charac' => $looser['perso']['id_character'],
                'type' => '1vs1',
                'nbLoose' => 1,
                'nbTotal' => 1
            ]);
        }   
    }

}