<tr>
    <td>
        <table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="content-cell" align="center">
                    {{-- Replace this line: --}}
                    {{-- {{ Illuminate\Mail\Markdown::parse($slot) }} --}}

                    {{-- With your custom footer --}}
                    <p style="font-size: 13px; color: #999;">
                        &copy; {{ date('Y') }} Sahtee Platform. All rights reserved.<br>
                        Thank you for using our services.
                    </p>
                </td>
            </tr>
        </table>
    </td>
</tr>
