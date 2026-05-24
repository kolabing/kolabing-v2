@extends('admin.layout', ['title' => 'Admin Login', 'hideHeader' => true])

@section('content')
    <div class="login-wrap">
        <div class="card">
            <div class="page-head">
                <div>
                    <h1>Admin Login</h1>
                    <p>Only maintainer accounts can access this panel.</p>
                </div>
            </div>

            <form method="post" action="/admin/login">
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                </div>

                <label class="checkbox" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Remember this device</span>
                </label>

                <div class="actions">
                    <button type="submit">Enter Panel</button>
                </div>
            </form>
        </div>
    </div>
@endsection
