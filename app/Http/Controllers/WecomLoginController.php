<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\Wecom\WecomClient;
use App\Services\Wecom\WecomNotConfigured;
use App\Services\Wecom\WecomRequestFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WecomLoginController extends Controller
{
    public function start(WecomClient $wecom): RedirectResponse
    {
        try {
            return redirect()->away($wecom->authorizationUrl());
        } catch (WecomNotConfigured) {
            return redirect()->route('wecom.denied')->with('reason', '企业微信还没有配置，请管理员先填写参数。');
        }
    }

    public function callback(Request $request, WecomClient $wecom): RedirectResponse
    {
        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('wecom.denied')->with('reason', '企业微信没有完成授权。');
        }

        try {
            $userId = $wecom->userIdFromCode($code);
        } catch (WecomNotConfigured) {
            return redirect()->route('wecom.denied')->with('reason', '企业微信还没有配置，请管理员先填写参数。');
        } catch (WecomRequestFailed $exception) {
            return redirect()->route('wecom.denied')->with('reason', $exception->getMessage());
        }

        $user = User::query()->where('wecom_userid', $userId)->first();

        if ($user === null) {
            return redirect()->route('wecom.denied')->with('reason', '请管理员先同步。系统不会在打开时自动建账号。');
        }

        if (! $user->canLogin()) {
            return redirect()->route('wecom.denied')->with('reason', '这个账号已停用，不能进入。');
        }

        Auth::login($user);

        return redirect()->route('me');
    }

    public function denied(): View
    {
        return view('wecom.denied', [
            'reason' => session('reason', '无法进入。'),
        ]);
    }

    public function me(): View
    {
        $user = Auth::user()->load(['store', 'counter']);
        $customers = Customer::query()
            ->where('owner_id', $user->id)
            ->orderBy('name')
            ->get();

        return view('wecom.me', [
            'user' => $user,
            'pendingCustomers' => $customers->whereNull('wecom_external_userid')->values(),
            'addedCustomers' => $customers->whereNotNull('wecom_external_userid')->values(),
        ]);
    }
}
