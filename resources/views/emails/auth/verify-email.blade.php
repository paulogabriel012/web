<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <title>{{ __('Verify Your Email - :app', ['app' => config('app.name')]) }}</title>
        <style>
            body,
            table,
            td,
            a {
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }

            table,
            td {
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
            }

            img {
                -ms-interpolation-mode: bicubic;
                border: 0;
                height: auto;
                line-height: 100%;
                outline: none;
                text-decoration: none;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: #fafafa;
            }

            .email-container {
                max-width: 600px;
                margin: 0 auto;
            }

            .email-header {
                background-color: #ffffff;
                padding: 30px 36px 28px 36px;
                text-align: center;
                border-bottom: 1px solid #e5e5e5;
            }

            .email-logo {
                display: block;
                max-width: 44px;
                height: auto;
                margin: 0 auto;
            }

            .email-content {
                background-color: #ffffff;
                padding: 38px 36px 42px 36px;
            }

            .email-title {
                font-size: 28px;
                font-weight: 600;
                color: #0a0a0a;
                margin: 0 0 14px 0;
                line-height: 1.25;
                letter-spacing: -0.02em;
            }

            .email-lead {
                font-size: 16px;
                color: #404040;
                line-height: 1.65;
                margin: 0 0 28px 0;
            }

            .email-text {
                font-size: 15px;
                color: #525252;
                line-height: 1.65;
                margin: 0 0 18px 0;
            }

            .notice-box {
                background-color: #fafafa;
                border: 1px solid #e5e5e5;
                border-radius: 8px;
                padding: 18px 20px;
                margin: 28px 0;
            }

            .notice-label {
                font-size: 13px;
                color: #737373;
                line-height: 1.4;
                margin: 0 0 8px 0;
            }

            .notice-text {
                font-size: 14px;
                color: #525252;
                line-height: 1.6;
                margin: 0;
            }

            .email-button-container {
                text-align: center;
                margin: 30px 0 34px 0;
            }

            .email-button {
                display: inline-block;
                padding: 14px 24px;
                background-color: #0a0a0a;
                color: #ffffff !important;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                font-size: 15px;
                line-height: 1.2;
            }

            .link-box {
                background-color: #fafafa;
                border: 1px solid #e5e5e5;
                border-radius: 8px;
                color: #525252;
                font-size: 13px;
                line-height: 1.5;
                margin: 0 0 28px 0;
                padding: 14px;
                word-break: break-all;
            }

            .email-footer {
                background-color: #fafafa;
                padding: 28px 36px;
                text-align: center;
                border-top: 1px solid #e5e5e5;
            }

            .email-footer-text {
                font-size: 13px;
                color: #737373;
                line-height: 1.55;
                margin: 0 0 10px 0;
            }

            .email-footer-text:last-child {
                margin-bottom: 0;
            }

            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                }

                .email-header,
                .email-content,
                .email-footer {
                    padding-left: 22px !important;
                    padding-right: 22px !important;
                }

                .email-title {
                    font-size: 24px !important;
                }

                .email-lead,
                .email-text {
                    font-size: 15px !important;
                }

                .email-button {
                    display: block !important;
                    text-align: center !important;
                }
            }
        </style>
    </head>
    <body>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #fafafa">
            <tr>
                <td style="padding: 40px 20px">
                    <table
                        role="presentation"
                        class="email-container"
                        cellspacing="0"
                        cellpadding="0"
                        border="0"
                        width="100%"
                        style="margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 12px; overflow: hidden"
                    >
                        <tr>
                            <td class="email-header">
                                <img src="{{ asset('apple-touch-icon.png') }}" alt="{{ config('app.name') }}" class="email-logo" />
                            </td>
                        </tr>

                        <tr>
                            <td class="email-content">
                                <h1 class="email-title">{{ __('Verify Your Email') }}</h1>

                                <p class="email-lead">
                                    {{ __('Confirm your email address to finish securing your :app account.', ['app' => config('app.name')]) }}
                                </p>

                                <p class="email-text">{{ __('Hello, :name.', ['name' => $user->name]) }}</p>

                                <p class="email-text">
                                    {{ __('Email verification protects your account and keeps important billing and security notifications deliverable.') }}
                                </p>

                                <div class="notice-box">
                                    <p class="notice-label">{{ __('Link expiration') }}</p>
                                    <p class="notice-text">
                                        {{ __('This verification link expires in :minutes minutes.', ['minutes' => config('auth.verification.expire', 60)]) }}
                                    </p>
                                </div>

                                <div class="email-button-container">
                                    <a href="{{ $verificationUrl }}" class="email-button">{{ __('Verify My Email') }}</a>
                                </div>

                                <p class="email-text" style="font-size: 14px">
                                    {{ __("If the button doesn't work, copy and paste this link into your browser:") }}
                                </p>

                                <p class="link-box">{{ $verificationUrl }}</p>

                                <p class="email-text" style="font-size: 14px; color: #737373">
                                    {{ __('If you did not create an account on :app, you can safely ignore this email.', ['app' => config('app.name')]) }}
                                </p>

                            </td>
                        </tr>

                        <tr>
                            <td class="email-footer">
                                <p class="email-footer-text">
                                    {{ __('This is an automated email related to your account.') }}
                                </p>

                                <p class="email-footer-text" style="font-size: 12px">
                                    {{ __('© :year :app. All rights reserved.', ['year' => date('Y'), 'app' => config('app.name')]) }}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
