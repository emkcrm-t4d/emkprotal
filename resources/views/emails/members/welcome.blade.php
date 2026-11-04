<x-mail::message>
# Welcome to EMK Center, {{ $memberName }}!

Congratulations! Your application for membership at American Spaces Bangladesh has been approved, and your official membership account is now active.

### Your Official Membership Details:
* **Member ID:** `{{ $memberId }}`
* **Membership Status:** Active
* **Valid From:** {{ $startDate }}
* **Expires On:** {{ $expiryDate }}

---

### Step 1: Activate Your Member Portal Account
Your self-service member portal account has been created. To set your personal password and access your profile, use the activation link below:

<x-mail::button :url="config('app.url') . '/member/activate'">
Activate Member Portal Account
</x-mail::button>

*(If the button above does not work, copy and paste the following URL into your browser: `{{ config('app.url') }}/member/activate`)*

---

### Step 2: Physical Membership Badge Printing
Your official plastic EMK Membership Card is currently queued for high-resolution printing. You will receive an notification as soon as your badge is ready for collection at the EMK Center front desk.

Please bring a photo ID or quote your Member ID (`{{ $memberId }}`) when picking up your badge.

If you have any questions or require assistance, please feel free to reach out to our front desk team at **info@emk.com.bd** or visit us during open hours.

Warm regards,  
**EMK Center Admissions & Membership Team**  
American Spaces Bangladesh
</x-mail::message>
