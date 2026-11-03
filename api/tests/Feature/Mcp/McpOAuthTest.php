<?php

namespace Tests\Feature\Mcp;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithWorkspace;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private const ENDPOINT = '/mcp/workspace';

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys')->assertSuccessful();
        }

        $this->setUpWorkspace();
    }

    public function test_unauthenticated_mcp_request_points_to_resource_metadata(): void
    {
        $response = $this->rpc('tools/list');

        $response->assertUnauthorized();
        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp/workspace').'"',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_discovery_metadata_is_published(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/workspace')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/workspace'))
            ->assertJsonPath('authorization_servers.0', url('/'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    }

    public function test_registration_rejects_unknown_redirect_domains(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [
            'client_name' => 'Claude Code',
            'redirect_uris' => ['http://localhost:54321/callback'],
        ])->assertCreated();
    }

    public function test_authorize_redirects_guests_to_login(): void
    {
        $clientId = $this->registerClient();

        $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, 'x')))
            ->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee('Entrar');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->withPassword($this->professional);

        $this->post('/login', ['email' => $this->professional->email, 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_full_oauth_flow_grants_mcp_access(): void
    {
        $token = $this->obtainAccessToken($this->withPassword($this->professional));

        $this->rpc('tools/call', ['name' => 'whoami', 'arguments' => (object) []], $token)
            ->assertOk()
            ->assertJsonPath('result.structuredContent.id', $this->professional->uuid);
    }

    public function test_oauth_token_keeps_client_isolation(): void
    {
        $token = $this->obtainAccessToken($this->withPassword($this->clientUserA));

        $this->rpc('tools/call', ['name' => 'get_task', 'arguments' => ['task_id' => $this->sharedTaskB->uuid]], $token)
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }

    public function test_oauth_token_requires_mcp_permission(): void
    {
        $token = $this->obtainAccessToken($this->withPassword($this->clientUserA));

        $this->roleFor($this->tenant, DefaultRole::CLIENT)->revokePermissions(Permission::MCP_USE);
        $this->clientUserA->unsetRelation('roles');

        $this->rpc('tools/list', token: $token)->assertForbidden();
    }

    public function test_oauth_token_cannot_be_used_on_rest_api(): void
    {
        $token = $this->obtainAccessToken($this->withPassword($this->professional));

        $this->withToken($token)->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_denying_consent_does_not_issue_code(): void
    {
        $user = $this->withPassword($this->professional);
        $clientId = $this->registerClient();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);
        $consent = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, Str::random(64))));
        $consent->assertOk()->assertSee('Autorizar acesso');

        $response = $this->delete('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => $consent->viewData('authToken'),
        ]);

        $this->assertStringContainsString('error=access_denied', (string) $response->headers->get('Location'));
    }

    private function withPassword(User $user): User
    {
        $user->forceFill(['password' => 'secret123'])->save();

        return $user;
    }

    private function obtainAccessToken(User $user): string
    {
        $clientId = $this->registerClient();
        $verifier = Str::random(64);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticated('web');

        $consent = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, $verifier)));
        $consent->assertOk()->assertSee('Autorizar acesso');

        $approve = $this->post('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => $consent->viewData('authToken'),
        ]);

        $location = (string) $approve->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk();

        $this->assertNotEmpty($tokenResponse->json('refresh_token'));

        // Requisições seguintes simulam o conector: sem sessão, só Bearer.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return (string) $tokenResponse->json('access_token');
    }

    private function registerClient(): string
    {
        return (string) $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::REDIRECT_URI],
        ])->assertCreated()->json('client_id');
    }

    /**
     * @return array<string, string>
     */
    private function authorizeParams(string $clientId, string $verifier): array
    {
        return [
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state-123',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * @param  array<string, mixed>|object  $params
     */
    private function rpc(string $method, array|object $params = [], ?string $token = null): TestResponse
    {
        $headers = ['Accept' => 'application/json, text/event-stream'];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ], $headers);
    }
}
