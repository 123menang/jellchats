<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Visitor;
use App\Models\Team;
use App\Services\AuthService;
use App\Services\RateLimiterService;
use App\Services\ChatService;
use App\Services\AIService;

final class App
{
    private static ?App $instance = null;
    private Database $db;
    private Router $router;
    private AuthService $auth;
    private RateLimiterService $rateLimiter;
    private ChatService $chatService;
    private AIService $aiService;
    private User $userModel;
    private Agent $agentModel;
    private Conversation $conversationModel;
    private Message $messageModel;
    private Visitor $visitorModel;
    private Team $teamModel;

    private function __construct()
    {
        // Load legacy helper functions before anything else
        require_once __DIR__ . '/../legacy_helpers.php';

        Session::initialize();

        $this->db = Database::getInstance();
        $this->db->runMigrations();
        $this->router = new Router();

        $this->userModel = new User($this->db);
        $this->agentModel = new Agent($this->db);
        $this->conversationModel = new Conversation($this->db);
        $this->messageModel = new Message($this->db);
        $this->visitorModel = new Visitor($this->db);
        $this->teamModel = new Team($this->db);

        $this->rateLimiter = new RateLimiterService($this->db);
        $this->auth = new AuthService($this->db, $this->userModel, $this->agentModel, $this->rateLimiter);
        $this->chatService = new ChatService($this->db, $this->conversationModel, $this->messageModel, $this->visitorModel);
        $this->aiService = new AIService($this->db);
    }

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function app(): self
    {
        return self::init();
    }

    public static function db(): Database { return self::init()->db; }
    public static function router(): Router { return self::init()->router; }
    public static function auth(): AuthService { return self::init()->auth; }
    public static function rateLimiter(): RateLimiterService { return self::init()->rateLimiter; }
    public static function chat(): ChatService { return self::init()->chatService; }
    public static function ai(): AIService { return self::init()->aiService; }
    public static function userModel(): User { return self::init()->userModel; }
    public static function agentModel(): Agent { return self::init()->agentModel; }
    public static function conversationModel(): Conversation { return self::init()->conversationModel; }
    public static function messageModel(): Message { return self::init()->messageModel; }
    public static function visitorModel(): Visitor { return self::init()->visitorModel; }
    public static function teamModel(): Team { return self::init()->teamModel; }

    public function run(): void
    {
        $request = new Request();

        $this->router->addGlobalMiddleware(function (Request $req, callable $next) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                http_response_code(204); exit;
            }
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            $next($req);
        });

        $this->registerRoutes();
        $this->router->dispatch($request);
    }

    private function registerRoutes(): void
    {
        require __DIR__ . '/../../routes/web.php';
    }
}
