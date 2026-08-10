<p>A new sponsorship inquiry was submitted on {{ config('app.name', 'Vertucio.com') }}.</p>

<table>
    <tr>
        <td><strong>Name</strong></td>
        <td>{{ $inquiry->full_name }}</td>
    </tr>
    <tr>
        <td><strong>Email</strong></td>
        <td>{{ $inquiry->email }}</td>
    </tr>
    <tr>
        <td><strong>Company</strong></td>
        <td>{{ $inquiry->company_name }}</td>
    </tr>
    <tr>
        <td><strong>Monthly budget</strong></td>
        <td>{{ $inquiry->monthly_budget }}</td>
    </tr>
</table>

<p><strong>Message</strong></p>
<p>{{ $inquiry->message }}</p>
