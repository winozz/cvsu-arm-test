<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
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
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = Str::lower(trim((string) $googleUser->getEmail()));

            if ($email === '' || ! Str::endsWith($email, ['@cvsu.edu.ph', '@gmail.com'])) {
                return $this->redirectWithError('Please use an authorized Google account to continue.');
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
}
