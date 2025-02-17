<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AdsIntegration;
use App\Models\AdsGoogleBusinessProfile;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class InviteAdminToGBPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $adsIntegration;
    protected $adsGoogleBusinessProfile;

    // Constructor to accept transaction ID or transaction object
    public function __construct(AdsIntegration $adsIntegration, AdsGoogleBusinessProfile $adsGoogleBusinessProfile)
    {
        $this->adsIntegration = $adsIntegration;
        $this->adsGoogleBusinessProfile = $adsGoogleBusinessProfile;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // Exchange refresh token for access token
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => $this->adsIntegration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($tokenResponse->failed()) {
            Log::error('Failed to refresh access token: ' . $tokenResponse->status());
            return;
        }

        $accessToken = $tokenResponse['access_token'];


        // Initialize Guzzle HTTP client
        $client = new Client();

        // Define the API endpoint and headers
        $url = 'https://mybusinessaccountmanagement.googleapis.com/v1/' . $this->adsGoogleBusinessProfile->google_business_profile_id . '/admins';

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        // Define the request body
        $body = json_encode([
            'admin' => env('GOOGLE_ADS_ADMIN_EMAIL'),
            'role' => 'MANAGER', 
        ]);

        // Send the POST request
        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            if ($response->getStatusCode() == 200) {
                Log::info('Admin invitation sent successfully.');
            } else {
                Log::error('Failed to send admin invitation. Status code: ' . $response->getStatusCode());
            }
        } catch (Exception $e) {
            Log::error('Failed to send admin invitation: ' . $e->getMessage());
        }

        // Wait for invitation to arrive and accept the invitation

        /*
        $adminTokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
            'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
            'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
            'grant_type' => 'refresh_token',
        ]);

        if ($adminTokenResponse->failed()) {
            Log::error('Failed to refresh access token: ' . $adminTokenResponse->status());
            return;
        }

        $adminAccessToken = $adminTokenResponse['access_token'];


        $url = "https://mybusinessaccountmanagement.googleapis.com/v1/accounts";
        $response = Http::withToken($adminAccessToken)->get($url);

        if ($response->failed()) {
            Log::error('Failed to fetch accounts');
            return;
        }

        $accounts = $response->json();

        if (empty($accounts['accounts'])) {
            Log::error('No accounts found');
            return;
        }

        $accountId = $accounts['accounts'][0]['name'];

        $invitations = [];
        $count = 0;
        do {
            // Use the account ID to get locations with a read_mask
            $inviteUrl = "https://mybusinessaccountmanagement.googleapis.com/v1/{$accountId}/invitations";
            $inviteResponse = Http::withToken($adminAccessToken)->get($inviteUrl);

            if ($inviteResponse->failed()) {
                Log::error('Failed to fetch invitations');
                Log::error($inviteResponse->body());
            }

            $invitations = $inviteResponse->json();
            $count++;

            // Wait for 1 second before fetching again
            sleep(1);

        } while (empty($invitations['invitations']) && $count < 30);

        if (empty($invitations['invitations'])) {
            Log::error('No invitations found');
            return;
        }

        $invitationId = $invitations['invitations'][0]['name'];

        $acceptUrl = "https://mybusinessaccountmanagement.googleapis.com/v1/{$accountId}/invitations/{$invitationId}:accept";
        $acceptResponse = Http::withToken($adminAccessToken)->post($acceptUrl);

        if ($acceptResponse->failed()) {
            Log::error('Failed to accept invitation');
            return;
        }

        Log::info('Invitation accepted successfully.');

        $this->adsIntegration->update([
            'gbp_admin_invitation_status' => 'completed',
        ]);
        */
    }
}
