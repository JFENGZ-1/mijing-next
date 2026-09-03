<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>微信 OpenID 绑定（开发工具）</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { margin: 0; padding: 24px; background: #f5f7fa; color: #1f2937; }
        h1, h2 { margin: 0 0 12px; }
        .muted { color: #6b7280; font-size: 14px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .flash { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; font-weight: 600; }
        form.inline { display: inline; }
        label { display: block; font-size: 13px; margin-bottom: 4px; color: #374151; }
        input, select { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        button { background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; cursor: pointer; }
        button.secondary { background: #6b7280; }
        button.danger { background: #dc2626; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .tag { display: inline-block; background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
    </style>
</head>
<body>
    <h1>微信 OpenID 绑定</h1>
    <p class="muted">仅供本地/开发运维使用，替代 <code>staff:bind-openid</code> 等 Artisan 命令。页面与日志仅显示 openid 末四位。</p>

    @if (session('success'))
        <div class="flash success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="flash error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <h2>微信应用</h2>
        <p class="muted">
            员工端 AppID：<code>{{ $staffAppid ?: '未配置' }}</code><br>
            会员端 AppID：<code>{{ $memberAppid ?: '未配置' }}</code>
        </p>
    </div>

    <div class="card">
        <h2>租户与场馆</h2>
        <table>
            <thead>
                <tr>
                    <th>租户</th>
                    <th>代码</th>
                    <th>场馆</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td>{{ $tenant->name }}</td>
                        <td><code>{{ $tenant->code }}</code></td>
                        <td>
                            @forelse ($tenant->sites as $site)
                                <span class="tag">{{ $site->name }} ({{ $site->code }})</span>
                            @empty
                                <span class="muted">无场馆</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">暂无租户，请先运行 system:bootstrap。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>绑定员工（staff 小程序）</h2>
        <form method="post" action="{{ url('/dev/wechat-bindings/staff') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="staff_employee_no">员工工号</label>
                    <select id="staff_employee_no" name="employee_no" required>
                        <option value="">选择员工</option>
                        @foreach ($staff as $row)
                            <option value="{{ $row->employee_no }}">
                                {{ $row->employee_no }} — {{ $row->name }} ({{ $row->tenant?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="staff_openid">OpenID（直接粘贴）</label>
                    <input id="staff_openid" name="openid" placeholder="来自 DevTools 或 wx.login">
                </div>
                <div>
                    <label for="staff_code">或 wx.login 一次性 code</label>
                    <input id="staff_code" name="code" placeholder="与 openid 二选一">
                </div>
            </div>
            <button type="submit">绑定员工</button>
        </form>
    </div>

    <div class="card">
        <h2>绑定会员（member 小程序）</h2>
        <form method="post" action="{{ url('/dev/wechat-bindings/member') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="member_no">会员编号</label>
                    <select id="member_no" name="member_no" required>
                        <option value="">选择会员</option>
                        @foreach ($members as $row)
                            <option value="{{ $row->member_no }}">
                                {{ $row->member_no }} ({{ $row->tenant?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="member_openid">OpenID（直接粘贴）</label>
                    <input id="member_openid" name="openid" placeholder="来自 DevTools 或 wx.login">
                </div>
                <div>
                    <label for="member_code">或 wx.login 一次性 code</label>
                    <input id="member_code" name="code" placeholder="与 openid 二选一">
                </div>
            </div>
            <button type="submit">绑定会员</button>
        </form>
    </div>

    <div class="card">
        <h2>当前绑定</h2>
        <h3>员工</h3>
        <table>
            <thead>
                <tr>
                    <th>工号</th>
                    <th>姓名</th>
                    <th>租户</th>
                    <th>场馆</th>
                    <th>App 类型</th>
                    <th>OpenID</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $row)
                    @php
                        $identities = $row->account?->wechatIdentities ?? collect();
                        $staffIdentity = $staffAppid ? $identities->firstWhere('appid', $staffAppid) : null;
                        $memberIdentity = $memberAppid ? $identities->firstWhere('appid', $memberAppid) : null;
                    @endphp
                    @foreach ([['staff', $staffIdentity], ['member', $memberIdentity]] as [$appType, $identity])
                        @if ($identity)
                            <tr>
                                <td><code>{{ $row->employee_no }}</code></td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->tenant?->name }}</td>
                                <td>{{ $row->sites->pluck('name')->join('、') ?: '—' }}</td>
                                <td>{{ $appType }}</td>
                                <td><code>{{ $maskOpenid($identity->openid) }}</code></td>
                                <td>
                                    <form class="inline" method="post" action="{{ url('/dev/wechat-bindings/unbind') }}" onsubmit="return confirm('确认解除该绑定？');">
                                        @csrf
                                        <input type="hidden" name="identity_id" value="{{ $identity->id }}">
                                        <button type="submit" class="danger">解绑</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="7" class="muted">暂无员工记录。</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3 style="margin-top: 20px;">会员</h3>
        <table>
            <thead>
                <tr>
                    <th>会员编号</th>
                    <th>租户</th>
                    <th>App 类型</th>
                    <th>OpenID</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $row)
                    @php
                        $identities = $row->account?->wechatIdentities ?? collect();
                        $memberIdentity = $memberAppid ? $identities->firstWhere('appid', $memberAppid) : null;
                    @endphp
                    @if ($memberIdentity)
                        <tr>
                            <td><code>{{ $row->member_no }}</code></td>
                            <td>{{ $row->tenant?->name }}</td>
                            <td>member</td>
                            <td><code>{{ $maskOpenid($memberIdentity->openid) }}</code></td>
                            <td>
                                <form class="inline" method="post" action="{{ url('/dev/wechat-bindings/unbind') }}" onsubmit="return confirm('确认解除该绑定？');">
                                    @csrf
                                    <input type="hidden" name="identity_id" value="{{ $memberIdentity->id }}">
                                    <button type="submit" class="danger">解绑</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="muted">暂无会员记录。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
