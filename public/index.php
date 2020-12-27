<?php
require __DIR__ . '/../vendor/autoload.php';
 
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
 
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;
 
$pass_signature = true;
 
// set LINE channel_access_token and channel_secret
$channel_secret = "";
$channel_access_token = "";
 
// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);
 
$app = AppFactory::create();
$app->setBasePath("/public");
 
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Halo Teman OFON!");
    return $response;
});
 
// buat route untuk webhook
$app->post('/webhook', function ($req, $res) use ($bot, $httpClient, $pass_signature)
{
    // get request body and line signature header
    $body = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '';
 
    // log body and signature
    file_put_contents('php://stderr', 'Body: '.$body);
 
    if($pass_signature === false)
    {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature))
        {
            return $res->withStatus(400, 'Signature not set');
        }
 
        // is this request comes from LINE?
        if (! SignatureValidator::validateSignature($body, $channel_secret, $signature))
        {
            return $res->withStatus(400, 'Invalid signature');
        }
    }
 
    $data = json_decode($body, true);
    if(is_array($data['events']))
    {
        foreach ($data['events'] as $event)
        {
            if ($event['type'] == 'follow')
            {
                if($event['source']['userId'])
                {
                    $userId     = $event['source']['userId'];
                    $getprofile = $bot->getProfile($userId);
                    $profile    = $getprofile->getJSONDecodedBody();
                    $greetings  = new TextMessageBuilder("Kamu dapat melakukan pencarian info tentang OFON dengan mengetikkan kata kunci yang akan diinformasikan.");
                    $onboarding = new TextMessageBuilder('Daftar Keyword :
Keyword : Daftar keyword
About : Menampilkan tentang OFON
Pbx : Info produk PBX
Solusi : Info produk solusi UC');

                    $multiMessageBuilder = new MultiMessageBuilder();
                    $multiMessageBuilder->add($greetings);
                    $multiMessageBuilder->add($onboarding);

                    $result = $bot->replyMessage($event['replyToken'], $multiMessageBuilder);
                    return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                }
            } elseif ($event['type'] == 'message')
            {
                if($event['message']['type'] == 'text')
                {
                    $message = strtolower($event['message']['text']);
                    if ($message == 'keyword')
                    {
                        $textMessageBuilder = new TextMessageBuilder('Daftar Keyword :
Keyword : Daftar keyword
About : Menampilkan tentang OFON
Pbx : Info produk PBX
Solusi : Info produk solusi UC');
                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);

                        return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                    } elseif ($message == 'about')
                    {
                        $textMessageBuilder = new TextMessageBuilder('OFON adalah layanan telepon tetap sesuai kode area geografis yang diselenggarakan oleh PT. Batam Bintan Telekomunikasi. Layanan Telepon Tetap OFON berbasis IP, yang terus dikembangkan dan dikelola sehingga tetap relevan dan adaptif terhadap kebutuhan telekomunikasi bisnis dan pola hidup masa kini.');
                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                        return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                    } elseif ($message == 'pbx')
                    {
                        $flexTemplate = file_get_contents("../flex_message_pbx.json");
                        $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                            'replyToken' => $event['replyToken'],
                            'messages'   => [
                                [
                                    'type'     => 'flex',
                                    'altText'  => 'Test Flex Message',
                                    'contents' => json_decode($flexTemplate)
                                ]
                            ],
                        ]);
                    } elseif ($message == 'solusi')
                    {
                        $flexTemplate = file_get_contents("../flex_message_solusi.json");
                        $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                            'replyToken' => $event['replyToken'],
                            'messages'   => [
                                [
                                    'type'     => 'flex',
                                    'altText'  => 'Test Flex Message',
                                    'contents' => json_decode($flexTemplate)
                                ]
                            ],
                        ]);
                    }else
					{
						$textMessageBuilder = new TextMessageBuilder('Maaf, keyword yang anda masukkan tidak dikenal. Silahkan ketik "keyword" untuk melihat daftar keyword yang tersedia.');
						$result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
						return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
					}
                }
            }
        } 
    }

    return $res->withStatus(400, 'No event sent!');
});

$app->run();