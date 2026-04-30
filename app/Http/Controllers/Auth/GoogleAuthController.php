<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        if (! $this->hasGoogleConfiguration()) {
            return $this->redirectWithError('Google sign-in is not configured yet. Please contact the administrator.');
        }

        /** @var GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google
            ->with([
                // 'hd' => ['cvsu.edu.ph'],
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if (! $this->hasGoogleConfiguration()) {
            return $this->redirectWithError('Google sign-in is not configured yet. Please contact the administrator.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $email = Str::lower(trim((string) $googleUser->getEmail()));

            if ($email === '' || ! Str::endsWith($email, ['@cvsu.edu.ph', '@gmail.com'])) {
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                return $this->redirectWithError('Your account must be added by an administrator before you can sign in.');
            }

            if (! $user->canUseGoogleSignIn()) {
                return $this->redirectWithError('Your account is inactive. Please contact the administrator.');
            }

            $user->syncGoogleProfile($googleUser->getId(), $googleUser->getAvatar());

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            $dashboardRoute = $user->dashboardRoute();

            if (! $dashboardRoute || ! Route::has($dashboardRoute)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $this->redirectWithError('Your account does not have an accessible dashboard yet.');
            }

            return redirect()->route($dashboardRoute);
        } catch (Throwable $exception) {
            Log::error('Google authentication failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectWithError('Authentication failed. Please try again.');
        }
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->route('login');
    }

    private function redirectWithError(string $message)
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    private function hasGoogleConfiguration(): bool
    {
        $config = config('services.google');

        $isConfigured = filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null)
            && filter_var($config['redirect'], FILTER_VALIDATE_URL) !== false;

        if (! $isConfigured) {
            Log::error('Google OAuth configuration is incomplete.', [
                'client_id_present' => filled($config['client_id'] ?? null),
                'client_secret_present' => filled($config['client_secret'] ?? null),
                'redirect' => $config['redirect'] ?? null,
            ]);
        }

        return $isConfigured;
    }
}
