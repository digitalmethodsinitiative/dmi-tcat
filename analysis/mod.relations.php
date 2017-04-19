<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/functions.php';
ini_set('memory_limit', '3G');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Relation graph</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">



        </script>

    </head>

    <body>

        <h1>TCAT :: Relation graph</h1>

        <?php
        validate_all_variables();
        dataset_must_exist();
        $dbh = pdo_connect();
        pdo_unbuffered($dbh);
        $collation = current_collation();

        if (empty($esc['mysql']['from_user_name']))
            die('<br><Br>please use a set of users in the from user field');
        //print "start";
        $sql = "SELECT user1_id, user2_id FROM " . $esc['mysql']['dataset'] . "_relations r, " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= " AND t.from_user_id = r.user1_id AND type = 'friend' GROUP BY user1_id, user2_id ORDER BY user1_id, user2_id";
        $q = $dbh->prepare($sql);
        $edges = array();
        if ($q->execute()) {
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $edges[$r['user1_id']][] = $r['user2_id'];
            }
        } else
            die('relations query failed');
        //print "edges loaded<br>";

        $allusers = $originals = array();
        foreach ($edges as $user1 => $users2) {
            $allusers[] = $user1;
            $originals[] = $user1;
            $acv = array_count_values($users2);
            unset($edges[$user1]);
            foreach ($acv as $v => $c) {
                $edges[$user1][$v] = $c;
                $allusers[] = $v;
            }
        }
        $allusers = array_unique($allusers);
        //print "edges recounted<br>";
        $sql = "SELECT id, screen_name FROM " . $esc['mysql']['dataset'] . "_users WHERE id IN ( " . implode(",", $allusers) . ")";
        $q = $dbh->prepare($sql);
        $usernames = array();
        if ($q->execute()) {
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $r) {
                $usernames[$r['id']] = $r['screen_name'];
            }
        } else
            die('users query failed');

        $sql = "SELECT from_user_id, from_user_name FROM " . $esc['mysql']['dataset'] . "_tweets WHERE id IN ( " . implode(",", $allusers) . ") group by from_user_id";
        $q = $dbh->prepare($sql);
        $originals_extended = array();
        if ($q->execute()) {
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $r) {
                $originals_extended[$r['from_user_id']] = $r['from_user_name'];
            }
        } else
            die('users lookup failed');
        unset($allusers);

        $csv = file('cache/users_classified.csv');
        $classifications = array();
        for ($i = 1; $i < count($csv); $i++) {
            $e = explode("\t", $csv[$i]);
            $account = trim(strtolower($e[0]));
            $classifications[$account]['affiliation'] = $e[1];
            $classifications[$account]['list'] = $e[2];
        }
        //print "classifications loaded<br>";
        $antieu = array('1509Sonnema','17165ea9dbca44d','78roontje','81dvw','a2e43a2a2fc2482','aafkevultink','AbenSander','ActumBerkmeer','AdemaJR91','AdjiedjBakas','adlijmbach','AgnesWelsh','AHJvanGool','ahtlam','alaphilipcocu','Alefcar13072016','ALOUTAIBA','AlptekinAkdogan','AmpersUK','AnalPoetNL','andre6707824','andrew_amaniel','AnnaKaleta1','AnnCoulter','anoniem_de','Ans__Aarsema','AntiJihadActie','AntonieNiessen','antoon_huigens','aqwan_music','ArchieBunkerbom','Aristochronos','ArjanCK','Armageddon_GS','ArnoldGreidanus','arthurmaas','AsgharBukhari','asifonly1','AugustusKozlovh','aus_einem_Guss','AzcWoudrichem','Az_coupons','Babbelghem','balbec2001','Bart1608','BartZijp','BasDuizend','BasharAlAssad83','bassieElswhood','bejeve','benadeelt','Benavra','beno1604','BenvanBakel','BertBouwknegt','BertRTV','betalenbepalen','Bethesda213','Beursbengeltje','beytullova','BiaColeman69','biancaguruita','BiFiBE','birdutterance','bitterzurig','BlikopNOS','BloemenBeppie','boekenopener','Boogschutter10','Borne_NL','BramSomers','BritishDemocrat','bruinklaasma','Buldmaster','burgercomiteeu','cafeweltschmerz','caitlynnicole29','Calimer0c0mplex','CalzelunghePipi','Cameronnyes','Captain2Cadans','CarolineVonhoff','carrolltrust','CARTOONISThugo','CassTete1','CathieJacoby','Cedantarmatogae','Cernovich','Change_Britain','charlesxavier61','ChaseCarbon','chauvinist1961','ChefOltman1','CheshirePicture','ChrisTheMacMan','CHRSHNSLR','Cindekel','ClemensP89','clintsschelleke','cmsNetherlands','constanteyn','coravanvliet2','corwindesheim','CosmicD','Cosmopolitka','CraigJWilly','cretanadia','Cris100G','crissirafa24','Crystallugia','cute_t_ful','d82726499fe44c4','DagDesOordeels','DaidaloDaedalus','Dame2010','danceprom','DaniDonblizz','DanielJeyn','DanielLouisCss','davemetwiter','deadwood1976','deepvanbinnen','DeGlasblazers','deniedhumrights','dennis20159','dennistak','Dennis_v_n','denuchterheid','DessartPatrick','DeStephenPeters','De_Belhamel','De_Regent','DickKraaij','diederikdegroot','Dieproodlove','Diggrich_','DiosadeluzBella','Disruptia','djharm60','DMAppelbaum','DMWorldLAev','dndcc','doarproatlub','doenormaal_1','dokkie27','douwemees','dprins128','dragnslyr_ds','Dreameovakantie','DrGertJanMulder','DriesseWout','Dries_Mulder','DrJPearson','dunc058','duxmerda','DwarsDoorTLeven','DWRDLDDRD','EBV06','Echt_Rechts','EdenredBelgie','edje007','EdWardMDBlog','edwin_koning','eenvoudige','EkskluzywneNET','elenafreedman','Eliannuminas','EmileElfferich','English_Woman','Eppo49','Erinzeller5','ErnestOtte','ernesto_spruyt','erwinc35','EUDataP_Plow','EuropeanDogg','EvaXXXXA','EWdeVlieger','ExposingBBC','fa1524791','FaabXJR','FabiusDryxos','Factinat0r','FBrand2','FedUpAmerican5','FeodulaLarys','FeyeNody','FffMachiavelli','Fidelio_is_weer','FinaTimmer','fleming77','FlikFluimsnor','flipfreriks','Flip_Switch','FlorodNL','flrskkrmn','FLUYTKETEL','ForzaCastricum','forzahmeer','forzaijmond','FrankfurtFinanz','frankgrauzone','freddyrosink','frgroenendijk','FritsCordoba','frpesogi','FSwissmartini','FTC_kawakami','Fub_Fub','fur3creations','Gasuniek','Gbjefr','GeekJenJen','Geenpeil053','GeenpeilHLMR','GeertenWaling','GekkoLupker','Gelaarsdekat_','gentlegiant8','gertvandoorn','GetergdeBurger','Gjoene','Gladstoned666','Glipto','Glomulti','GNeIIieNL','GNellieNLD','goddersbloom','GoodeJonnhy','GraalGrondwater','GrahamWP_UK','Gregiorius','GuidoClicque','GusFarrow','Halle000','HannibalPim','hannigancork','HansAckerNY','Hans_C_G','HarmenFS','harrystolker','Harry_Dillema','harvey_rodri34','hbroekhuisen','HellHound2015','Helmamaatje','Henk_Haarlem','henriett_louise','hermanbenschop','hermannkelly','HertoghsLute','hiertommy','hlelowrold','hmvandenbosh','HolidayyRentals','HolladieJan','HouVanCis','hoveling','huizer56','HutspotMetMayo','huybertt','H_J1966','IainDale','Iamdutch01','IchEintovenaar','ietsist','Iko_Nal','illquitto','ilona1','Immigrant_X','Infocadl2015','IngeGem','Inthepeninsula','jabkees','Jack55Joop','JackyJMK','JamesRon1980','JamesSpivey1','jan1950smits','Jane0brien','jangajentaan','JanMorlode','jansen_mila','JanTrommelen1','Januszdoradca','JaredBeck','jasondewaard','jasonsavard','jasperconway1','JB_Hilterman','JDuin68','jeannemarian','JefHorians','jeroendonker','JeroenvanH','Jglammi','jjmello','joffrey25','Johan5324','johaster','johnnybegood757','JohnsonTurpin','Jongderik','JoostB17','JoostNiemoller','Jopijoho','Jordy_Schaap','JosdeBoer7','jos_de_graaf','JScherpenberg','JuliusSeg','jurrytl','JWilsonGB','k2_recherche','kalemol','kameyasadako','kareltjevdtuin','karinbreeuwer','KarsAbbes','keesvloon','Kees_Stam','KellieMattie75','Kersb','KiamilBehti','kiekebosch','kneistonie','knipoog50','koentje4242','KolodzejDavid','KoosNoordzij','Kopernek','Kornilov1968','krapnek','kretologie','KretserK','Kridem','Kruisruiker','KSLPinto','KTHopkins','kurzanov','LammertAlbertus','Lancelot451','Langelaan1','laryda24','LBC','LboroNO2EU','ledukeepon6','leemakiyama','LefebveM','leondewinter','LesbiaBaziev','LeylandTigerr','lgorhythmical','liberty_sells','Lisa_Kruithof','Li_RM35M4419','LnMcglwn848','LogicalLorena','lonesomebanker','lorenzolameass','lounge_act023','LouTerLou','LSpokeman','luckyjimsling','LuckyTrade76','Luideraad','luisjeindepels','LuisMrblog2012','lw88452','l_ruigrok','MaartenDevant','maartented','macharoesink','madelyn_atkins','madraq','Mahesvari_','MaikelHelias','majamathea','MajaMischke','Malinka1102','marceldedood','marcoocram2304','marcorbosch2','MarcusLooijman1','mariekehoogwout','marina_saniram','MarionAltena','Marios020','marizsmn','markbnl','Marko_Kahn','martingilbraith','martybrennan','maryfloor','maureenkarin','MaxKommer','MaxSchoo','MaxvanderWerff','melanchton1','MelanieLatest','MeneerBint','mening_geven','Meriadoc','MerlotVine','mersinvekili','mgihara','MichaelvdGalien','michelCvisser','micheleggermont','MichelSchep','Michel_oordeelt','michkapteijns74','mieke2','MiepieB','Miguelencasa','Milos1389Obilic','minister_Blok','Mirjam152','mjhjongeneel','mklerx','mmulder1972','MoggeWieringen','mohammedbawa98','monaeberhardt','MoosOliemans','MOverboord','MrGoodVotes','MrMHoney','MTB070','mxbiker85','mybestremedies','MyOpinions4you','M_R_TEMPEL','naked_short','nandoecolumnist','NasaleNeusaap','natalia7420','Ndriana','NederPiet','neemjemoeder1','NEJRoberts','NepelGert','news_letter_pro','NEXITIUS','NickFerrariLBC','Nickje64','Niek1953','NiekvLeeuwen','nietcorrupt','Nilliz81','Nix020','NL4EqualUkraine','noknokitsme','nosurrender65','Obey__Daquonna','Oedinger01','ogssie','okkupant186','olivermorgans','oli_linden','OmeFoXX','OogOpDeOverheid','OpvliegersOp1','OrbisDomum','orthopeer','Osiris1973','OsscarVillaseca','ouwebanaan','P1Verstappen','pabloshmablo','Palestineglobal','PaPaAlbertios','pappamerro','participassant','PatrickHilsman','patricksavalle','PaulApostate','paulmasonnews','Payperback','pazer77','pbartels7','PcExtremist','PCIMeijer','PDubonnet','Perry010_','petersiebelt','petervlelieveld','PGvandenBerg1','phillyxam','pieterloos63','PieterSlootdorp','Piggelmee','pimbrussee','pjm56tw','plasmodelable','PMO_W','politicalgee','PowellPolitics','Powerboxie','Prikkerr','PrisonPlanet','Pritt','PVerkaik1','PVVbedreigingen','queenofunseen','RabiaatRechts','rabredewold','Rachael_Swindon','RaheemKassam','raklijnstra','RamazanGuveli','Ramsterdam','rayzb92','RCM_Doherty','RdamsSchoffie','realssidorov','Real_Cindersm7','real_saint22','redogan64','RedStrapperMX','ReinBijlsma','renevangellekom','richard_jk','rickyhelp','rietvandon','Rightousone2015','rivliv','rjmponsen','RJMStoops','RMiltenburg','RobertKnijff','robittybobnob','robvandepas','RoelandRuijsch','RoelfTurksema','RogerBakker','RolandBrouwer78','Rolo_Tamasi','RomerHenk','ronald_brok','Ronald_Plasterk','Ronald_Vermeer','RonvanHerreveld','Roovers410','rotkerel','Roxaereon','rrdoetjes','rstextile','RTRAtlanta','Rudpren','RuiCosta474','RussiaConnects','RutgervdNoort','rvanroon','RyanDriehuys','r_hartman','sadcosmonaut','Saleheim','samuelmelle','Sam_Schulman','SaradePerse','sarahjhammer4','SarahSj942','Schorpioen1988','SCYEugene','secret_ledger','SetxuDGZiganda','seven__','ShaylaDakins372','ShinobiNinja','ShitDredging','shut_tfup_donny','siegheiligman','siepkuppens1','silver_stacker','SimoneLaurey','sipkeveen','SjoerdRuigrok','Sjon_m','SkyFallCarroll','SLagaja','SLECHTVOLK','snookerfan33','snooziflooze','soetersnico','SondagesCompare','Sophia4980','spacema09104056','springbok60','spunjzz','steen020','steve_borg','stmc1964','stokeontoast1','Stormtroepen','Stripesman','suaviter9','SubsidiarityMan','suekenmax','Sunita_Biharie','susannekendler','SvensTweet','swatzutphen','tafkatp','TaniaOram','tanker070','Tante_Frolic','TarakNL','Taxidriver69','taxidrivergaz','teapot_russells','Terror_Oehoe','teunvaandering','TheFactCompiler','thefrost','Thegiest','thehugheslady','TheNiceBot','theovisser_','therasmus10','theuniszen','thheidstra','thickopedia','ThijsDuz','Tim0Richards','timjsheard','Timodc','tjemigdepemig','Tj_vanderMeer','tm_johannes','Toekomst4','tommycl1','top_grafisch','toua0108','Toxic_Troubles','TresBleu33','treyptrsn','truusdemierr','TurkeyMedia','tvdh_3','twilight0972','TwittBot001','twittelzie','TwitterBusiness','tylerusesoap','T_TurtonUKIP','umarebru','union_european','uri4u','utkuottawa','uwedimo','VALENTINE_O_','valk1955','varnhem07','verahofste','Verbraak','Verheijen_Mike','verNederlander','VictorKortijs','VilmaAlvarezrvl','VincentVoogd','visuele','vleeskroket','volkstribunaal','VriesS','VrijdenkerDavid','vrijheid_mania','vtblr','V_of_Europe','wally_soute','WarHorse573','Wauwelwok','WaveScript','weijers3','WestVeryBest','WevertonLu','whitty0702','why_color','wierdduk','WiesCools','William230616','WilliamTassler','willibrordia','willyreiss','wimgrommen','wimh57','WimKoning','wjb_67','Woewillem','wolf_wim','xandralammers','Xifidion','xtbes','Yamapama','YNatsis','YouKnowMe_MrP','yourparliament','zaagvis','ZanzibarZorro','zerohedge','ZilteBotte','zilterzout','zoalsdewaardis','ZURBfoundation','ZusterG','Zwijgerspreekt','zzzmarie1','_Stijlvol_','_X_acerbation','__Sabs');
        $fnv = array("BloemenBeppie", "Eppo49", "pbartels7", "Iamdutch01", "SjoerdRuigrok", "Oedinger01", "Lisa_Kruithof", "HellHound2015", "GraalGrondwater", "NasaleNeusaap", "Sylvester_RT", "marcoocram2304", "Terror_Oehoe", "Tante_Frolic", "ZanzibarZorro");
        $aeu = array("rutgervdnoort", "robertknijff", "drgertjanmulder", "roelfturksema", "ziltebotte", "jongederik", "pvvbedreigingen", "forzacastricum", "jordy_schaap", "benavra", "jangajentaan", "rabiaatrechts", "gelaarsdekat", "nexitius", "fluytketel", "GNellieNLD", "Opvliegersop1", "theuniszen", "Oekrainee_eu");
        foreach ($antieu as $user) {
            $classifications[$user]['affiliation'] = 'anti-eu-full';
            $classifications[$user]['list'] = 'anti-eu-full';
        }
        foreach ($fnv as $user) {
            $classifications[$user]['affiliation'] = 'fvd';
            $classifications[$user]['list'] = 'fvd';
        }
        foreach ($aeu as $user) {
            $classifications[$user]['affiliation'] = 'anti-eu-core';
            $classifications[$user]['list'] = 'anti-eu-core';
        }

        $filename = get_filename_for_export("relations", "", "gdf");
        $handle = fopen($filename, "w");
        fwrite($handle, "nodedef>name VARCHAR,label VARCHAR, affiliation VARCHAR, list VARCHAR\n");
        foreach ($usernames as $id => $name) {
            $namel = strtolower($name);
            $affiliation = $list = "n/a";
            if (array_search($id, $originals) !== false) {
                $affiliation = "starting_points";
                $list = "starting_points";
            } elseif (isset($classifications[$namel])) {
                $affiliation = trim($classifications[$namel]['affiliation']);
                $list = trim($classifications[$namel]['list']);
            }
            fwrite($handle, $id . "," . $name . "," . $affiliation . "," . $list . "\n");
        }

        fwrite($handle, "edgedef>node1 VARCHAR,node2 VARCHAR,weight DOUBLE,directed BOOLEAN\n");
        foreach ($edges as $user1 => $users) {
            foreach ($users as $user2 => $weight) {
                fwrite($handle, $user1 . "," . $user2 . "," . $weight . ",true\n");
            }
        }
        fclose($handle);

        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        ?>

    </body>
</html>