<?php

if ($argc < 1)
    die; // only run from command line
// ----- params -----
set_time_limit(0);
error_reporting(E_ALL);
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../common/constants.php';
include_once __DIR__ . '/../../common/functions.php';
include_once __DIR__ . '/../common/functions.php';

require __DIR__ . '/../common/tmhOAuth/tmhOAuth.php';

$screennames_list = array(
    //"lijsttrekker_aanvallers" => array("AJonkhart","Bugs0007","DrGertJanMulder","Heerbean","JackPecozi","MartienHoute","Mayonaise100","PepijndeKorte","RAetREchte1","RalphGeest","Stormtroepen","WaerdtC","YidArmyNL","defret5","doenormaal_1","ger2591","hbroekhuisen","heuvel874","janjanssen1533","janklaassen1533","joke82260431","klaasvaak1533","longtallbaldy","mening66","veranderwens"),
    //"lijsttrekkers_likers" => array("1234Leenders","1LuckyWorld","7d8c4ed5622a48a","7eKamer","81dvw","AlexStraver","Allochtoons","AngeliqueWinder","Astriddegroot70","BackofficeGP","ChristeldeHaas","Classicon2","CorryLieverse","De_Regent","Don_Mascarpone","E_Hunyadi","Elimelech_Ring","FLSpringintveld","FreeHolland0","GMlovelie67xxx","GeertJeelof","GraalGrondwater","Grannny63","Hallyfax","Heelgewonevrouw","Heerbean","HellHound2015","JackPecozi","Jaspolitiek","JohanGrijzen","Johan_Driessen","Klefbek","LavieJanRoos","Malouiner","MarcdeGroot4","MarieJoseGMH","Matthijs85","Mazda_111","Merfralex","MijnGetweet","MrsPHSingh","MuslimInEurope","NieuwewegenNu","Nina72Nebula","Nokterian","PartijvdDieren","Peterscruff55","PiratePartyHI","PiratenGrn","Piratenpartij","PtrRkrs","RealDutchRoots","RebeccaFaussett","RechtenRadboud","RobbieRietdijk","Robwoltjer","RomeijndersK","RooieSpieker","Seriedad_porfi","SonjaPlomp","Stormtroepen","Tante_Frolic","TerlouwArie","Terror_Oehoe","TomvanderLee","TweedeKamerTwit","VNL153","VNLaanhangers","VitalMoors","VoorNederland","WaerdtC","WimSomers","YidArmyNL","ZanzibarZorro","adamsmithvnl","amg_weststrate","andrevanwanrooy","bastilladigital","caroline_pers","cdavandaag","christenunie","cmsNetherlands","cyrilAFCA023","d_heuver","dirkvano","ffl641","fvdemocratie","ger2591","heuvel874","jndkgrf","kanny081995","l_escala","l_ruigrok","lenifillekes","marizsmn","maxmonkau","mees_c","mening66","ncilla","neemjemoeder1","ogssie","petrakramer","pewe63","reinywielsma","ricobrouwer","script_lady","veranderwens","verderwellief","volgvarken","volgzwijn","vpartijbureau","windwens"),
    "civil_society_youth_org" => array("studentenbond ","vakbond ","fnvjong ","jobmbo ","NJRtweets ","haagsestudent ","isostandards ","izisolutions ","wijzijnmorgen ","JRtweets ","LSBOleiden ","SooZwolle ","srvubond ","VIDIUS_Utrecht ","VSSD ","AKKUbestuur ","gronstudbond"),
    //"verdacht" => array("bezoekme", "BarthCarlo", "GobyTweets", "ogssie", "adrianxleconte", "DeRealist2016", "Hallyfax", "hepadie", "mythar1966", "Revelsoffice", "ben_jasperse", "1LuckyWorld", "annepeeters123", "juriekaptijn", "RinussieNL", "GraalGrondwater", "HellHound2015", "Terror_Oehoe", "PietHeinNov1577", "P_Spoonert"),
    //"verdacht" => array("peilmanneke", "theuniszen", "inigo_says", "Jaspolitiek", "Flip_Switch", "sandervanluit", "zlatabrouwer", "burgercomitenl", "Gatestone_NL", "GatestoneInst", "EStobbelaar", "empverhey", "ernesto_spruyt"),
    //"spindoctors" => array("engels_hans", "MaartenHijink", "MeyerRon", "JAWCJanssens", "Royovitz", "SybrenKooistra", "Henrikruithof", "mennodebruyne", "ddwsp", "SimondenHaak", "DaanBonenkamp", "BartvdBrink", "LauraHuisman", "sjirkkuijper", "jonathanvdgeer", "Baserlings", "wouterkokx", "Iankooye", "barrysmit", "diederiktencate", "sesajas", "BasEickhout", "GerKoopmans", "KeesBerghuis","karstenklein","hugodejonge","fritshuffnagel","fleurgraeper","miekepennock","meuspoel","jandriessen"),
        //"politiek_journalisten" => array("JaapJansen", "JoostVullings", "AviBhikhie", "fritswester", "JosHeymans", "TjerkBos", "Jorn", "XandervdWulp", "Nielsrigter", "bertvleeuwen", "thijsniemant", "Evansteenbergen", "stephankoole", "HaagseComedie", "RobertGiebels", "BartTrouw", "remcomeijervk", "EricVrijsen", "GwenTerHorst", "alexanderbakker", "JeroenStans", "pdekoning", "AriejanKorteweg", "LeonardOrnstein", "devriesjoost", "christiaanpe ", "IngeLengton", "DenhartogT", "ronfresen", "fonslambie", "JosHeymans", "JacoAlberts2"),
        //"dagbladen" => array("volkskrant", "nrc","trouw","telegraaf","adnl","fd_nieuws","parool"),
        /* "50plus_kandidaten" => array("50pluspartij", "HenkKrol", "LeonieSazias", "mj_vanrooijen", "CvanBrenk", "SGeleijnse", "Emilebode", "wschrover", "koopman_maurice", "VohmVoorzitter", "theunwiersma", "Goedele1", "PetravanVeeren", "JanFonhof", "monivdgriendt", "wimvanoverveld", "f_kerkhof", "Adriana_Hdez_", "theoheere", "JaapHaasnoot_KK", "joopbeteross", "Rosa_Molenaar", "arnohaye", "olgademeij", "andrevanwanrooy", "jolanda_v_hulst", "rhiannagralike", "Drenthe50PLUS", "GoedhartRob", "ChrisVeeze", "pietenmieke", "dickdefries", "GertsenHans", "StadsbelangGor", "HJtC1948", "harmbos", "miekehoek", "Struijlaard"),
          "artikel_1_kandidaten" => array("Art1kel", "SylvanaSimons", "FatimaFaid", "BrigitteSins", "jensvantricht", "Iankooye", "SvanSaarloos", "martijndekker", "MarianellaLeito", "anneruthw", "kobus16"),
          "cda_kandidaten" => array("cdavandaag", "sybrandbuma", "MonaKeijzer", "RenePetersOss", "PieterOmtzigt", "MvanToorenburg", "Raymondknops", "PieterHeerma", "harryvdmolen", "HankeBruinsSlot", "JacoGeurts", "AnneKuik", "ChrisvanDamCDA", "AgnesMulderCDA", "michelrog", "MustafaAmhaouch", "Martijncda", "ErikRonnes", "JobavdBerg", "EvertJanSlootwe", "poortvli", "Hilde_PM", "JHTerpstra", "StSteenbakkers", "vivianneheijnen", "swdenbak", "chrisschotman", "arjanerkel", "karinzwinkels", "janhutten", "marischakip", "evanmierlo", "riadekorte", "bobbergkamp", "janjaapdehaan", "JochgemvOpstal", "BGardeniers", "wiljan_vloet", "mvonmartels", "RogierHavelaar"),
          "christenunie_kandidaten" => array("christenunie", "gertjansegers", "carolaschouten", "joelvoordewind", "carladikfaber", "eppobruins", "StienekevdGraaf", "DonCeder", "H_Vreugdenhil", "NicoDrost", "joellegooijer", "gerbenhuisman", "GerdienRots", "BertTijhof", "Pieter_Grinwis", "havlieg", "LeonMeijer_", "MECHeuvelink", "SanderFoort", "watreurniet", "GerardMostert", "SimoneKennedy", "esamebid", "HaroldHofstra", "ackumar070", "jetweigand", "estherkaper", "TheoKrins", "hschuring", "AnkievTatenhove", "jannyjoosten", "LMZuidervaart", "bjaspersfaijer", "IngeJongman", "1jessedehaan1", "DicoBaars", "fcvisser", "AnnaCKlein", "FarshidMehdi", "elskooijwerk", "cjvankranenburg", "gert_vd_berg", "IxoraSB", "CU_vanzaalen", "anjahaga", "Ronvanderspoel", "Henkstoorvogel", "arievanderveer"),
          "d66_kandidaten" => array("d66", "APechtold", "SvVeldhoven", "ivanengelshoven", "piadijkstra", "wkoolmees", "KeesVee", "Vera_Bergkamp", "jpaternotte", "Paul_van_Meenen", "svanweyenberg", "swsjoerdsma", "RobJetten", "JessicaVanEijs", "GroothuizenD66", "SalimaBelhaj", "RensRaemakers", "achrafboualiD66", "AntjeDiertens", "TjeerdD66", "d66monica", "MatthijsSienot", "RutgerSchonis", "Sneller", "arendmeijer", "FrancaEurlings", "munishramlal", "MartinevBemmel", "jaimivanessen", "kristieLamers", "DinaVerbrugge", "Jeanetvdlaan", "bastiaanwinkel", "Zarroy", "npsanders", "RachidG", "MpanzuBamenga", "HulyaKatOnline", "EelcoKeij", "MarijnBosman", "SietzeSchukking", "ThierryVanVugt", "janlona", "StefanWirken", "CorinevanDun", "NellekeD66", "BertTerlouw"),
          "denk_kandidaten" => array("denknl", "tunahankuzu", "f_azarkan", "selcukozturknl", "GladysDENK", "StephanvBaarle", "MagdalenaCharl3", "taimounti", "MKoolbergen", "AlitsouliD66", "Marit_vSplunter", "1RabiaKaraman", "zahirrana2"),
          "de_burgerbeweging_kandidaten" => array("Burgersinbewegi", "DBurgerBeweging", "advlems", "ankesiegers", "Ubuntunederland", "AugustoTitar", "DirkDubling", "ElovenaAckerman", "ErikHolthuis", "BosFrieda", "NachtwachtNL", "GioGezinsCoach", "hvsteenbergen", "hugoschonbeck", "usepresentchild", "jolandakirp", "JWilshaus", "KittyHaccou", "lexhupe", "LFBervoets", "MarcoFinFreedom", "DBBcornielje", "WayraMarnix", "MarijkeHanff", "Ninavdburgt", "PeaceInDesign", "renegraafsma", "RoulaTourgaidis", "stralingsvrij"),
          "forum_voor_democratie_kandidaten" => array("fvdemocratie", "thierrybaudet", "THiddema", "Susan_teunissen", "PFrentrop", "SusanStolze", "Gert_Reedijk", "YerRamautarsing", "ZlataBrouwer", "caroladieudonne", "godertvanassen", "GeertJeelof", "luke_boltjes", "Sander_O_Boon", "Astriddegroot70", "HemmieKerklingh", "hvelzing", "loekvanwely"),
          "geenpeil_kandidaten" => array("GeenPeil", "jndkgrf", "BerylDreijer", "AhmedAarad", "DamiaanR", "chantalklaver", "AlptekinAkdogan", "NdeS_77", "GeertJohan", "vivscontent", "GiebelsSander", "AliBal88", "maartenbrante", "marceldedood"),
          "groenlinks_kandidaten" => array("groenlinks", "jesseklaver", "kathalijne", "TomvanderLee", "lindavoortman", "rikgrashoff", "GroenLiesbeth", "CorinneEllemeet", "ZihniOzdil", "bartsnels", "BramvanOjikGL", "suzanne_GL", "NevinOzutok", "PaulSmeulders", "Lisawesterveld", "LauraBromet", "wimjanrenkema", "Huibvanessen", "IsabelleDiks", "iliasmahtab", "Cathelijne_GL", "CedericDuboisch", "mayaVirg", "arnobonte", "CircularMy", "jnkuipers", "JanetDuursma", "armaganonder", "volkertvintges", "SamirBashara", "huubbellemakers", "Cora_Smelik", "Sophie_Zukini", "michelk", "roccopiers", "PaulVermast", "marjoleinesnaas", "Kevinvanoort"),
          "jezus_leeft_kandidaten" => array("JEZUSLEEFT_pp", "Florens0148"),
          "libertarische_partij_kandidaten" => array("LPnl", "ValentineRW", "arnoinenlp", "arnoinen", "tallmanplans", "netwerknathan", "mwh684", "QuintusBackhuys", "kaajeewee", "JuanvanGinkel", "NagtegaalSjors", "Palletier", "ToineManders"),
          "lokaal_in_de_kamer_kandidaten" => array("lokaalindekamer", "janheijman", "janstarre", "Ralfke1977", "jeffleever", "DeniseKunst", "rakraaijenbrink", "EdithWillemJan", "wilvanpinxteren", "royal32a", "Faridbsd", "FSmitFzn", "JanKwekkeboom", "@RonRosbak", "ans_vd_velde", "HenkHenkfrieman", "AnnetteValent", "connytrots", "ramonbarends", "DirkmaatGijs", "casperkloos"),
          "mens_en_spirit_basisinkomen_partij_kandidaten" => array("Mens_en_Spirit", "vrede_recht", "BIpartij", "SylvieJacobs", "Yvonbrinkerink", "rob_vellekoop", "FerdinandZanda", "p_vlug", "ingrid_schaefer", "BeerAnnemarie", "AshwinKaris", "ErikaMauritz", "BertKroek"),
          "niet_stemmers_kandidaten" => array("NietStemmers", "peter_plasman", "WvvWillem", "maribel_schwab", "RemcoRhee", "ritsplasman", "rijkplasman"),
          "nieuwe_wegen_kandidaten" => array("NieuwewegenNu", "JacquesMonasch", "Ramoon_74", "TonSpitsbaard", "Wendyvianen", "arrrie76", "IJochems", "SietseJan75", "RomeoDurgaram", "Yvonnedevrieszu", "jaco_verstappen", "bovanscheyen", "daviddejong90", "jeanetpijpker", "HWillemstein", "eddie3140", "ruudvangessel", "Simone_deBoer", "BuchelLeo"),
          "ondernemerspartij_kandidaten" => array("opnederland", "herobrinkman", "Martine_1973", "JosDeVries_OP", "kicken", "SophieANDRIOL", "rg_bol", "har_reits", "woodclan"),
          "pvv_kandidaten" => array("pvv", "geertwilderspvv", "FleurAgemaPVV", "GidiMarkuszower", "Martinbosma_pvv", "lilianhelderpvv", "harmbeertema", "GraafDeMachiel", "RidderDionGraus", "r_deroonpvv", "edgarmulder1", "KarenGerbrands1", "leondejong", "g_popken", "KopsPVV", "housmans74", "henkdevree", "MaxPVV", "ToonvanDijk_PVV", "deWinter_D", "IlseBezaan", "JoyceKardol", "ElmarVlottes", "Robdejong023", "AvPijkeren", "jrleefbaar", "HanIJssennagger", "henkvandeun", "TimVermeer", "OlafBuitelaar1", "Maikel_PVV", "edbraam", "MennoLudriks", "louisroks", "FolkertThiadens", "eliasvanhees", "AWJAvanHattem", "OlafStuger"),
          "piratenpartij_kandidaten" => array("Piratenpartij", "ncilla", "Matthijs85", "ricobrouwer", "AlexStraver", "PMHDowns", "tjerkfeitsma", "larsjanssen_", "Bob_Sikkema", "MichielDulfer", "PiraatRHuurman", "dielemanbas", "gpeskens", "jhmarseille", "DHallegraeff", "JoranTibor", "interminded", "AdvocaatAlpha", "oudheusl", "verrukkulluk", "mstubbe", "daveborghuis", "evanluxzenburg", "WimDool", "JeeeeeeeDeeeeee", "Nokterian", "teung", "Loulou_Raven", "zieglerfloor", "khalidahmedch", "N_is_Een"),
          "pvda_kandidaten" => array("PvdA", "LodewijkA", "khadijaArib", "J_Dijsselbloem", "sharon_dijksma", "dijkvangijs", "attjekuiken", "henknijboer", "wmoorlag", "JohnKerstens", "JokedeKock", "ahmedmarcouch", "marithvolp", "RichardMoti", "keklikyucel", "michielservaes", "EmineBozkurt", "IlcovanderLinde", "ChristaOosterb", "MoMohandis", "Loesypma", "jeroenrecourt", "MaritMaij", "MartijnVdeK", "ReshmaRoopram", "gisellekens", "albvri", "mirthebiemans", "meilivos", "JReinaerts", "BouchraDibi", "VanDrooge", "Amma_Asante", "joycevermue", "dekken_van", "AnnaLenaPx", "bob_deen", "cindyvorselman", "ErikPentenga", "Roelof", "MarinkaMulder", "WimarBolhuis", "SultanGunal", "JelmerStaal", "ElsBoot", "MChahim", "Petradkr", "RoyBreederveld", "lauramenenti", "RichardvdBurgt", "LouRepetur", "SaamiAkrouh", "AnitaEngbers", "ThomasRonnes", "WijntuinP", "FredCohen1947", "AnneDankert1", "IVoigt", "Klomp73", "marco_keizer", "TirzaHouben", "EricvantZelfde", "ssdoevendans", "foppe_de_haan","kirstenvdhul", "annavdboogaard", "heinvanh", "tschrofer"),
          "pvdd_kandidaten" => array("PartijvdDieren", "mariannethieme", "estherouwehand", "LammertvanRaan", "WassenbergFrank", "FemkeMerel", "EvavanEschPvdD", "Ct_teunissen", "ewaldeng", "Johnasvlammeren", "EvaAkerboom", "bramvanliere", "PvdDvanderWel", "LuukvanderVeer", "CarlavanViegen"),
          "sgp_kandidaten" => array("SGPnieuws", "keesvdstaaij", "elbertdijkgraaf", "BisschopRoelof", "hjaruissen", "chris_stoffer", "gpschipaanboord", "JanKloosterman", "jptanis", "JoostVeldman", "ldknegt", "ajflach", "WimSGPUtrecht", "GertvL", "EwartBosma", "Wimstweeter", "VanWestreenen", "hansvantland", "PNoordergraaf", "WoutervandBerg", "markbrouwer2010"),
          "sp_kandidaten" => array("Spnl", "emileroemer", "RenskeLeijten", "MarijnissenL", "SadetKarabulut", "SandraBeckerman", "MichielvNispen", "peterkwint", "bartvankent", "CemLacin", "FrankFutselaar", "NineKooiman", "MaartenHijink", "JaspervanDijkSP", "EricSmaling", "MahirAlkaya", "HenkvGerven", "DiederikOlders", "TonHeerschop", "SPadjes", "BasMaes", "MeyerRon", "Sun_Yoon", "ddwsp", "bertpeterse", "arnouthoekstra", "hansboerwinkel", "pattyhamerslag", "BBuskoop", "nicoletemmink", "NdRidder", "DeniseSluijs", "aisha_30", "jimmydijk", "inekebekkering", "staarink", "nilsmuller", "fennaf", "mheuw", "renskehe"),
          "stemnl_kandidaten" => array("RedactieStemNL", "MariovdEijnde", "MarcelFicken", "Willie_StemNL", "WernerHessing", "mfontein", "joyce_herp", "StemNL_Leiden"),
          "vvd_kandidaten" => array("VVD", "MarkRutte", "MinPres", "JeanineHennis", "HalbeZijlstra", "tamaravanark", "dijkhoff", "sanderdekker", "BarbaraVVD", "markharbers", "hantenbroeke", "MalikAzmani", "DennisWiersma", "HelmaLodders", "bvantwout", "bentebecker", "PDuisenberg", "Sophie_Hermans", "a_mulder", "aukjedevries", "DilanYesilgoz", "arnorutte", "ockjetellegen", "danielkoerhuis", "erikziengs", "andrebosman", "ZelYassini", "remcovvd", "Worsdorfer", "ArneWeverling", "cnijkerken", "sjoerdpotters", "foortvanoosten", "SvenKoopmansVVD", "Jan_Middendorp", "RoaldLinde", "joostvankeulen", "AntoinetteLaan", "Meerdoemama", "HaykeVeldman", "RudmerHeerema", "Wybrenvanhaga", "leendertdelange", "tobiasvangent", "jeroendeveth", "vanwijngaardenj", "ThierryAartsen", "RegterschotK", "bsmals", "mirjampauwels", "MBolkestein", "marksnoeren", "JaccoHeemskerk", "Tonjann", "jandereus1", "RosemarijnDral", "IreneKorting", "ArendsKathy", "krijnlock", "falcohoekstra", "andrevanschie", "ErikStruijlaart", "TanjaHaseloop", "barryjacobs_", "JennyElbertsen", "Sspringen", "JasperMos", "LindaBocker", "RoelanddeRijk", "yvowel", "NickDerks", "Mirandajoziasse", "Harry_Bevers", "prinsvvd", "HHuismanTexel", "Laurinebon", "DLochtenberg", "crlarson", "zeefNL", "mvdweijden"),
          "voornederland_kandidaten" => array("voornederland", "laviejanroos", "JoramvKlaveren", "Louis_Bontes", "TanyaHoogwerf", "sassenvanelsloo", "MichelVersteeg1", "jan_de_laat", "VanSymen", "PeterVermaas9", "j_himpers", "hjoosterhagen1", "AdsumoEU", "tigg47", "LJAJStassen"),
          "vrijzinnige_partij_kandidaten" => array("vpartijbureau", "KleinNorbert", "Loonenwerk", "Artemis_on_Mars", "misterracesport", "Lammers_Ruud", "YohanVPpolitiek", "antoon_huigens", "LucasStassen"),
         */
);


// ----- connection -----
$dbh = pdo_connect();
$ratefree = $current_key = $looped = 0;

foreach ($screennames_list as $bin_name => $screennames) {

    print "\n\nStarting $bin_name\n";

    print "\nGetting ids\n";

    $mapped = map_screen_names_to_ids($screennames);
    $users = array_values($mapped);

    print "found (" . count($users) . "/" . count($screennames) . "):";

    $user_ids = $users;
    $list_name = "";
    $type = 'follow';

    $querybin_id = queryManagerBinExists($bin_name);

    $ratefree = $current_key = $looped = 0;

    print "Creating bin $bin_name\n";
    create_bin($bin_name, $dbh);
    queryManagerCreateBinFromExistingTables($bin_name, $querybin_id, $type, $user_ids);

    $tweetQueue = new TweetQueue();
    print "Retrieving timelines\n";
    foreach ($user_ids as $user_id) {
        get_timeline($user_id, "user_id");
    }

    if ($tweetQueue->length() > 0) {
        $tweetQueue->insertDB();
        queryManagerSetPeriodsOnCreation($bin_name);
    }

    if ($type == 'follow') {
        /*
         * We want to be able to track our user ids in the future; therefore we must set the endtimes to NOW() for this particular set.
         * The reason: when TCAT is asked to start a bin via the User Interface, it starts those users who share a maximum endtime (i.e. the most recently used set).
         */
        $sql = "SELECT id FROM tcat_query_bins WHERE querybin = :bin_name";
        $rec = $dbh->prepare($sql);
        $rec->bindParam(":bin_name", $bin_name, PDO::PARAM_STR);
        if ($rec->execute() && $rec->rowCount() > 0) {
            if ($res = $rec->fetch()) {
                $querybin_id = $res['id'];
                $ids_as_string = implode(",", $user_ids);
                $sql = "UPDATE tcat_query_bins_users SET endtime = NOW() WHERE querybin_id = :querybin_id AND user_id in ( $ids_as_string );";
                $rec = $dbh->prepare($sql);
                $rec->bindParam(":querybin_id", $querybin_id, PDO::PARAM_INT);
                $rec->execute();
            }
        }
    }

    // NOTE: This is certainly only for the TCAT 7 script, not something we always want to do, but it forces all follow bins to keep running
    $sql = "update tcat_query_bins_users set endtime = '0000-00-00 00:00:00'";
    $rec = $dbh->prepare($sql);
    $rec->execute();
}

function get_timeline($user_id, $type, $max_id = null) {
    print "doing $user_id\n";
    global $twitter_keys, $current_key, $ratefree, $looped, $bin_name, $dbh, $tweetQueue;

    $ratefree--;
    if ($ratefree < 1 || $ratefree % 10 == 0) {
        $keyinfo = getRESTKey($current_key, 'statuses', 'user_timeline');
        $current_key = $keyinfo['key'];
        $ratefree = $keyinfo['remaining'];
    }

    $tmhOAuth = new tmhOAuth(array(
        'consumer_key' => $twitter_keys[$current_key]['twitter_consumer_key'],
        'consumer_secret' => $twitter_keys[$current_key]['twitter_consumer_secret'],
        'token' => $twitter_keys[$current_key]['twitter_user_token'],
        'secret' => $twitter_keys[$current_key]['twitter_user_secret'],
    ));
    $params = array(
        'count' => 200,
        'trim_user' => false,
        'exclude_replies' => false,
        'contributor_details' => true,
        'include_rts' => 1,
        'tweet_mode' => 'extended',
    );

    if ($type == "user_id")
        $params['user_id'] = $user_id;
    else
        $params['screen_name'] = $user_id;

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

        // store in db
        $tweet_ids = array();
        foreach ($tweets as $tweet) {
            $t = new Tweet();
            $t->fromJSON($tweet);
            $tweet_ids[] = $t->id;
            if (!$t->isInBin($bin_name)) {
                $tweetQueue->push($t, $bin_name);
                print $t->created_at . "\n";
                //print ".";
                if ($tweetQueue->length() > 100)
                    $tweetQueue->insertDB();
            }
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
        get_timeline($user_id, $type, $max_id);
    } else {
        $error_code = json_decode($tmhOAuth->response['response'])->errors[0]->code;
        if ($error_code == 130) {
            print "Twitter is over capacity, sleeping 5 seconds before retry\n";
            sleep(5);
            get_timeline($user_id, $type, $max_id);
        } elseif ($error_code == 88) {
            print "API key rate limit exceeded, sleeping 60 seconds before retry\n";
            sleep(60);
            get_timeline($user_id, $type, $max_id);
        } else {
            echo "\nAPI error: " . $tmhOAuth->response['response'] . "\n";
        }
    }
}
