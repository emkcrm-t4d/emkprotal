<x-mail::message>
# Membership Application Received

Dear **{{ $applicantName }}**,

Thank you for applying for membership with the American Spaces Bangladesh network! Your application has been logged into our secure verification pipeline.

### Application Details
* **Application Reference ID:** `{{ $referenceCode }}`
* **Selected American Space:** {{ $targetSpace }}
* **bKash Transaction ID:** `{{ $bkashTxid }}`

---

### What Happens Next?
Our Information Desk and Admissions team will review your application against our three verification streams:

1. **Payment Verification:** We will confirm your bKash Transaction ID against our merchant records.
2. **Document Compliance Check:** Reviewing your uploaded National ID / Student ID / Birth Certificate (and parental consent form, if applicable).
3. **Studio Photo Check:** Verifying that your photo meets studio passport standards for card printing.

This process typically takes **2 to 3 business days**. Once approved, you will receive an invitation email to activate your Member Portal account and access your digital membership card.

If you have any questions or need to provide additional details, please contact our Information Desk at **info@emk.com.bd** with your reference code (`{{ $referenceCode }}`).

Warm regards,  
**EMK Center Admissions & Membership Team**  
American Spaces Bangladesh
</x-mail::message>
