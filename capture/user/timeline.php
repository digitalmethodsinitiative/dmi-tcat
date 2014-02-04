<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once "../../config.php";
include_once BASE_FILE . "/querybins.php";
include_once BASE_FILE . '/common/functions.php';
include_once BASE_FILE . '/capture/common/functions.php';

require BASE_FILE . 'capture/common/tmhOAuth/tmhOAuth.php';
// ----- connection -----
$dbh = pdo_connect();

//$bin_name = 'user_student_journalistiek';
// specify list of user_ids
//$user_ids = array('dijkie', 'aldus1', 'NinaJuffermans', 'Thomas_Michel', 'stevendev', 'jaccotoma', 'MvanLangevelde', 'Daanlangkamp', 'jansinnige', 'AnneKruijsen', 'PretoriusWerner', 'JoostKatoen', 'IrmgardKoenen', 'ParisLovePastry', 'jolienvangolde', 'RuttenMartijn', 'peetersNiels', 'WouterVagevuur', 'heugem029', 'Nikesnotes', 'Emile_O', 'EvaSteenhuizen', 'ericstok', 'acmol', 'siemhielkema', 'xDaniel91', 'ChrisVerweij', 'jmaouche', 'AntoonKanis', 'mark_zandvoort', 'WouterJan3', 'robbertruijsch', 'thomashassing', 'KellyvanAlphen', 'marthevanengen', 'PaulaV90', 'HenriekePaul', 'Sonnyvanstralen', 'IAMRAMONSTERLY', 'joostvanhilst', 'mipz89', 'JuliedeBruijn', 'milanvw', 'Merlelebrun', 'KeesVisser90', 'FreekSijm', 'Rikwashere', 'SAJBrouwers', 'Sywert', 'Achillaes', 'martvanderburg', 'brigittedeheij', 'whereisyvette', 'JelleMenges', 'marijesmits', 'simoncolumbus', 'AnnElJansen', 'GeoMeans', 'AniekVanKoot', 'larsvansusteren', 'Jariidegraaf', 'RickBouwmeister', 'mike_hengelo', 'Monotoonn', 'iPatrick2', 'daanseegers', 'Jeffreypennings', 'Revers11', 'Sjors_M', 'KMIkkersheim', 'KimmetjeS', 'lucas_verhelst', 'LuukRiet', 'willemdewolf', 'Ezzor', 'burobraaf', 'KevinDercks', 'RinodeBoer', 'Jolijnnnw', 'SophiiMa', 'RomanoVLD', 'RoosMandy', 'Marieke_degroot', 'KarenvdHeijden', 'rtbvanderlaan', 'hoppienieuwkoop', 'reiniers13', 'DannyloveAlicia', 'DdBraaf', 'CScholtes', 'sprivee', 'Freath', 'RobintenHoopen', 'marjoleinjvos', 'e_vitaa', 'BaardAad', 'KlaasJandeGroot', 'Yondischmidt', 'YantheKijkt', 'Matszs');
//$bin_name = "user_politiek_nl_lokale_politici";
//$list_name = "lokale politici";
// MEPs
//$user_ids = array('DavidCasaMEP', 'LyubchevaMEP', 'OlegValjalo', 'davorstier', 'SandraPetrovicJ', 'CorinaCretu_PSD', 'BirgitSippelMEP', 'baldini_marino', 'MaleticIvana', 'BiljanaBorzan', 'RuzaTomasic', 'bonaninifranco', 'JSaryuszWolski', 'verodekeyser', 'MonikaFlaBenova', 'ToineMandersEP', 'MatulaMEP', 'thaendel', 'SKMLatest', 'jpgauzes', 'MPatraoNeves', 'MichelDANTIN', 'GrzybAndrzej', 'GoulardSylvie', 'EUTheurer', 'JeanRoatta', 'TimKirkhopeMEP', 'ivajgl', 'JVitkauskaiteB', 'AlfSvenssonKD', 'miromikolasik', 'agneslebrun', 'ZuzanaRoithova', 'LenaBobinska', 'MarioMauro', 'RichardAshMEP', 'CharlesTannock', 'm_giannakou', 'ANiebler', 'jleichtfried', 'GuyVerhofstadt', 'TadeuszCymanski', 'ClaudeMoraesMEP', 'PetruLuhan', 'blochbihler', 'IvarsGodmanis', 'AnaGomesMEP', 'LibDemMEPs', 'NirjDeva', 'BLiberadzki', 'bratkowskipsl', 'SantiagoFisas', 'ProtasiewiczJ', 'PhilBennionMEP', 'AngelilliR', 'JensRohde', 'CharlesGoerens', 'CzSiekierski', 'PhBoulland', 'IsmailErtug', 'AstridLulling', 'AnniPodimata', 'JelkoKacin', 'SonikBoguslaw', 'matthiasgroote', 'CeciliaWikstrom', 'VeronikMathieu', 'HFlautre', 'ZalewskiPawel', 'RTaylor_MEP', 'elisabe4h', 'DanJoergensen', 'cottigny', 'mmigalski', 'jaatteenmaki', 'MarilenaKoppa', 'STRIFFLERM', 'GrosseteteF', 'AlainCadec', 'tarjacronberg', 'HynekFAJMON', 'SCaronna', 'PatricielloAldo', 'catherinemep', 'KrzysztofLisek', 'TokiaSaifi', 'ArnaudDanjean', 'G_Uggias', 'KarimZeribi', 'Weidenholzer', 'mojcakleva', 'EGardini', 'MartinCallanan', 'SarahLudfordMEP', 'MarinaMEP', 'MickeEU', 'GreenJeanMEP', 'JeanMarieCAVADA', 'Andrew_Duff_MEP', 'AntonioPanzeri', 'trzaskowski_', 'MilanZver', 'azanoniplus', 'ANDRESPERELLO', 'franckproust', 'FionaHallMEP', 'DianeDoddsMEP', 'DerekClarkMEP', 'TPicula', 'PaulRuebig', 'desarnez', 'OlgaSehnalova', 'WernerKuhnMdEP', 'Elena_Basescu', 'rozathun', 'EvaOrtizVilella', 'datirachida', 'sabincutas', 'paulmurphymep', 'a_werthmann', 'NiccoloRinaldi', 'CatherineGreze', 'dajcstomi', 'SERGIOBERLATO', 'rafbaladassarre', 'j_klute', 'satuhassi', 'MarielleGallo', 'Tatjana_Zdanoka', 'DJazlowiecka', 'juliegirling', 'RCortesLastra', 'AxelVossMdEP', 'TizianoMotti', 'pavelpoc', 'NChildersMEP', 'kazakmetin', 'MitroRepo', 'EvaJoly', 'AndreasMoelzer', 'SajjadKarimMEP', 'antolinsp', 'PervencheBeres', 'EurofractieSGP', 'IneseVaidere', 'BerraNora', 'MLP_officiel', 'GerardBattenMEP', 'paulnuttallukip', 'Isabelle_Durant', 'NSinclaireMEP', 'MCVergiat', 'lidiageringer', 'petervdalen', 'SlaviBinev', 'jwojc', 'EuroLabour', 'Frederiqueries', 'ViktorUspaskich', 'LvNistelrooij', 'andreykovatchev', 'JanMulderEU', 'Sophie_Auconie', 'statarella', 'UlrikeLunacek', 'richardhowitt', 'IvailoKalfin', 'MBENARABATTOU', 'sylvieguillaume', 'LindaMcAvanMEP', 'MaireadMcGMEP', 'ph_lamberts', 'RiaOomenRuijten', 'brunogollnisch', 'Cabrnoch', 'comilara', 'AnnaZaborska', 'diogo_feio', 'AGL_Live', 'SanchezSchmid', 'FrCastex', 'GastonFranco', 'clegrip', 'SyedKamall', 'marctarabella', 'zgurmai_EN', 'Evelyn_Regner', 'pcanfin', 'Joanna_Senyszyn', 'anjaweisgerber', 'SharonBowlesMEP', 'fkaczmarek', 'othmar_karas', 'TonoEPP', 'Catalin_Ivan', 'younousomarjee', 'libertarofuturo', 'romanajordan', 'davidmartinmep', 'KatkaNevedal', 'Engstrom_PP', 'Ili_Ivanova', 'Cristian_Busoi', 'RogerHelmerMEP', 'ACozzolino', 'estellegrelier', 'GlenisWillmott', 'ramontremosa', 'JerzyBuzek', 'robertszile', 'GPapastamkos', 'PTirolien', 'RiikkaPakarinen', 'gualtierieurope', 'tfajon', 'emorinchartier', 'SidoniaJ', 'krisjaniskarins', 'gesine_meissner', 'ycochet', 'pawelkowalpl', 'ChrisDaviesMEP', 'nickgriffinmep', 'LucasHartong', 'JillEvansMEP', 'karsenis', 'GuidoMilana', 'KarimaDelli', 'AParvanova', 'MicheleRivasi', 'Cofferati', 'sebastianbodu', 'EmmaMcClarkin', 'OnMarioPIRILLO', 'Loekkegaard_MEP', 'andrewbronsmep', 'toiapatrizia', 'DanHannanMEP', 'adamkosamep', 'iatsoukalas', 'robertrochefort', 'JosephDaul', 'MartinHaeusling', 'IKasoulides', 'JLMelenchon', 'mcashmanMEP', 'PatrickLeHyaric', 'r_czarnecki', 'FidanzaCarlo', 'damienabad', 'epdonskis', 'mareksiwiec', 'SlawomirNitras', 'HortefeuxBrice', 'mehrenhauser', 'TheBrusselite', 'ER_Korhola', 'DCBMEP', 'JeanJacobbicep', 'wolejniczak1', 'F_Alfonsi', 'chdeveyrac', 'PaulNuttallMEP', 'jskrzydlewska', 'RobertaMetsola', 'DavidSassoli', 'RichardFalbr', 'delcastillop', 'anargomes', 'jfostermep', 'PrendergastMEP', 'debackerphil', 'hans_van_baalen', 'teirdes', 'joachimzeller', 'peterliese', 'FredericDaerden', 'AdinaValean', 'martinkastler', 'raulromeva', 'Skylakakis', 'LiamAylward', 'vitalmoreira09', 'CarmenRomero09', 'AdamBielan', 'Andrikiene', 'luispalves', 'Landsbergis', 'Saudargas', 'SalvadorSedo', 'kaminskimichal', 'trevorcolman', 'renateweber', 'Chatzimarkakis', 'vigenin', 'hreul', 'zofijamazej', 'LDomenici', 'europamayer', 'MargreteAuken', 'BSJ_EP_2009', 'buba0769', 'EuropaJens', 'BarbaraMatera', 'vadimtudor', 'Jaakonsaari', 'billnewtondunn', 'indrektarand', 'Claude_Turmes', 'balcytis', 'Hannes_Swoboda', 'STRUANSTEVENSON', 'fbrantner', 'corinnelepage', 'Andreas_Schwab', 'Gerbrandy', 'OjulandK', 'Rodi_Kratsa', 'ETurunen', 'AnnaMariaCB', 'Esther_de_Lange', 'UlrikeRodust', 'BReimers', 'Eric_Andrieu', 'mandreasen', 'EmineBozkurt', 'MarianHarkin', 'alexandrathein', 'SoniaAlfano', 'Kalniete', 'MiguelPortas', 'JudithMerkies', 'Vincent_Peillon', 'NilsTorvalds', 'ngriesbeck', 'ioanmirceapascu', 'kaderarif', 'henriweber', 'bvergnaud', 'gillespargneaux', 'PabloZalba', 'harlemdesir', 'IvoBelet', 'MarijeC', 'CarlSchlyter', 'danycohnbendit', 'jpbesset', 'sandrinebelier', 'josebove', 'reimerboege', 'AlynSmithMEP', 'nadjahirsch', 'Goddersukip', 'SaidElKhadraoui', 'editeestrela', 'thijsberman', 'olleludvigsson', 'Fjellner', 'derekvaughan', 'maritaulvskog', 'kozusnik', 'SeanKellyMEP', 'CarlosCoelhoPE', 'emcmillanscott', 'elisaferreira', 'EuroMP_ArleneMc', 'DRothBehrendt', 'bueti', 'ManfredWeber', 'maryhoneyball', 'PeterSkinnerMEP', 'ghokmark', 'judithineuropa', 'BasEickhout', 'schmidtblogg', 'MarietjeSchaake', 'knufleckenstein', 'BartStaes', 'AnnaHedh', 'gfarm', 'SophieintVeld', 'WimvandeCamp', 'AsaWestlund', 'Nigel_Farage', 'JuttaSteinruck', 'C_Stihler_MEP', 'robiols', 'georgelyonmep', 'jeanlucdeheane', 'grahamwatsonmep', 'caspary', 'HolgerKrahmer', 'berndlange', 'JlBennahmias', 'AlexAlvaro', 'JanAlbrecht', 'yannickjadot', 'sven_giegold', 'SkaKeller', 'Koch_Mehrin', 'eva_lichti', 'ruitavares', 'vickyford', 'giannipittella', 'philippejuvin', 'emercostello', 'paolodecastro');
$user_ids = array();
include_once("sotsji.php");
$bin_name = "user_sotsji_athletes";
$list_name = "";

// instead of specying usernames, you can also fetch usernames from a specific list in the database
if (!empty($list_name)) {
    $q = $dbh->prepare("SELECT list_id FROM " . $bin_name . "_lists WHERE list_name = '" . $list_name . "'");
    if ($q->execute()) {
        $list_id = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $list_id = $list_id[0];
        $q = $dbh->prepare("SELECT user_id FROM penw_lists_membership WHERE list_id = $list_id");
        if ($q->execute()) {
            $user_ids = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        }
    }
}

if (empty($bin_name))
    die("bin_name not set\n");
if (empty($user_ids))
    die("user_ids not set\n");

$current_key = $looped = 0;

create_bin($bin_name, $dbh);

foreach ($user_ids as $user_id) {
    get_timeline($user_id);
}

function get_timeline($user_id, $max_id = null) {
    print "doing $user_id\n";
    global $twitter_keys, $current_key, $looped, $querybins, $bin_name, $dbh;

    $tmhOAuth = new tmhOAuth(array(
                'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
                'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
                'token' => $twitter_keys[$current_key]['twitter_user_token'],
                'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
            ));
    $params = array(
        'screen_name' => $user_id, // you can use user_id or screen_name here
        'count' => 200,
        'trim_user' => false,
        'exclude_replies' => false,
        'contributor_details' => true,
        'include_rts' => 1
    );

    if (isset($max_id))
        $params['max_id'] = $max_id;

    $tmhOAuth->user_request(array(
        'method' => 'GET',
        'url' => $tmhOAuth->url('1.1/statuses/user_timeline'),
        'params' => $params
    ));

    //var_export($params); print "\n";

    if ($tmhOAuth->response['code'] == 200) {
        $tweets = json_decode($tmhOAuth->response['response'], true);

        // check rate limiting
        $headers = $tmhOAuth->response['headers'];
        $ratelimitremaining = $headers['x-rate-limit-remaining'];
        $ratelimitreset = $headers['x-rate-limit-reset'];
        print "remaining API requests: $ratelimitremaining\n";

        if ($ratelimitremaining == 0) {
            $current_key++;
            print "next key $current_key\n";
            if ($current_key >= count($twitter_keys)) {
                $current_key = 0;
                $looped = 1;
                print "resetting key to 0\n";
            } elseif ($current_key == 0 && $looped == 1) {
                if (count($tweets) > 1)
                    $looped = 0;
                else {
                    print "looped over all keys but still can't get new tweets, sleeping\n";
                    sleep(5);
                }
            }
        }

        // store in db
        $tweet_ids = array();
        foreach ($tweets as $tweet) {
            $t = Tweet::fromJSON(json_encode($tweet)); // @todo: dubbelop
            $tweet_ids[] = $t->id;
            $saved = $t->save($dbh, $bin_name);
            print ".";
        }

        if (!empty($tweet_ids)) {
            print "\n";
            if (count($tweet_ids) <= 1) {
                print "no more tweets found\n\n";
                return false;
            }
            $max_id = min($tweet_ids);
            print "max id: " . $max_id . "\n";
        } else {
            print "0 tweets found\n\n";
            return false;
        }
        sleep(1);
        get_timeline($user_id, $max_id);
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_timeline($user_id, $max_id);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_timeline($user_id, $max_id);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}

?>
