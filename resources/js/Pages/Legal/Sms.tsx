import LegalLayout, { List, Section } from '@/Layouts/LegalLayout';
import { Link } from '@inertiajs/react';
import { Alert } from '@/Components/ui/alert';

interface Props {
    company: { name: string; address: string; contact_email: string };
    sms: { program_name: string; help_email: string; help_phone: string };
    appName: string;
    effectiveDate: string;
    smsOptInText: string;
}

export default function Sms({
    company,
    sms,
    appName,
    effectiveDate,
    smsOptInText,
}: Props) {
    const link = 'font-medium text-indigo-600 underline hover:underline';

    return (
        <LegalLayout title="SMS Terms" effectiveDate={effectiveDate}>
            <Alert variant="warning" role="status" className="p-3">
                {appName} does not send text messages yet. These terms describe
                how our text messaging program will work when it launches, and
                apply once you opt in to it.
            </Alert>

            <p>
                These SMS Terms describe the "{sms.program_name}" text messaging
                program operated by {company.name}. They supplement our{' '}
                <Link href={route('legal.terms')} className={link}>
                    Terms of Service
                </Link>{' '}
                and{' '}
                <Link href={route('legal.privacy')} className={link}>
                    Privacy Policy
                </Link>
                .
            </p>

            <Section title="1. The program">
                <p>
                    {sms.program_name} sends text messages to people who have
                    opted in. Messages relate to the {appName} service, such as:
                </p>
                <List>
                    <li>account and security alerts;</li>
                    <li>
                        notifications about jobs and quotes you are involved in
                        (for example, a status change or a request that needs
                        your attention);
                    </li>
                    <li>
                        replies to a message you sent us, and responses to HELP
                        and STOP requests.
                    </li>
                </List>
                <p>
                    We do not send promotional or marketing text messages, and
                    we do not use text messages for debt collection or to send
                    anything unrelated to the {appName} service.
                </p>
            </Section>

            <Section title="2. How you opt in">
                <p>
                    You opt in by entering your mobile number in the app and
                    ticking the separate, unchecked box that asks for your
                    agreement to receive text messages. It reads:
                </p>
                <blockquote className="border-s-4 border-gray-300 ps-4 text-gray-600">
                    "{smsOptInText}"
                </blockquote>
                <p>
                    This agreement is separate from, and not required by, your
                    agreement to the Terms of Service and Privacy Policy. We
                    keep a record of your agreement, including the date and
                    time, the wording shown and your phone number.
                </p>
                <p>
                    <strong>Shops that message their own customers.</strong> If
                    you use {appName} to send text messages to your own
                    customers or other contacts, you must first obtain each
                    person's own consent to receive them, in clear language that
                    identifies you as the sender, describes the messages,
                    frequency and rates, and explains how to opt out. Consent is
                    never a condition of buying from you. You must honor
                    opt-outs immediately, and we may record, check and block
                    messages that do not meet these requirements.
                </p>
            </Section>

            <Section title="3. Message frequency, cost and carriers">
                <List>
                    <li>
                        Message frequency varies depending on your activity and
                        settings.
                    </li>
                    <li>
                        <strong>Message and data rates may apply</strong>,
                        according to your mobile plan. We do not charge for the
                        messages themselves.
                    </li>
                    <li>
                        Delivery is subject to your carrier's network and
                        coverage. Carriers and {company.name} are not liable for
                        delayed or undelivered messages.
                    </li>
                    <li>
                        Participating carriers include major U.S. carriers; the
                        list may change and is not exhaustive.
                    </li>
                </List>
            </Section>

            <Section title="4. Opting out (STOP) and getting help (HELP)">
                <List>
                    <li>
                        Reply <strong>STOP</strong> to any message to cancel. We
                        will send one confirmation message and then no more. You
                        can also opt out in the app.
                    </li>
                    <li>
                        Reply <strong>HELP</strong> to any message for help, or
                        contact us at {sms.help_email} or {sms.help_phone}.
                    </li>
                    <li>
                        To start receiving messages again after opting out, opt
                        in again in the app, or reply START.
                    </li>
                </List>
                <p>
                    Opting out of text messages does not affect your account or
                    the emails we send about it.
                </p>
            </Section>

            <Section title="5. Your information">
                <p>
                    We collect and use your mobile number and message history
                    only to provide this program and as described in our{' '}
                    <Link href={route('legal.privacy')} className={link}>
                        Privacy Policy
                    </Link>
                    . No mobile information will be shared with third parties or
                    affiliates for marketing or promotional purposes.
                    Information sharing to subcontractors in support of
                    services, such as customer care or message delivery, is
                    permitted. All other categories exclude text messaging
                    originator opt-in data and consent; this information will
                    not be shared with any third parties.
                </p>
            </Section>

            <Section title="6. Changes">
                <p>
                    We may change this program or these SMS Terms. If a change
                    is material we will tell you before it takes effect and,
                    where required, ask for your consent again. Continuing to
                    receive messages after the effective date means you accept
                    the updated terms.
                </p>
            </Section>

            <Section title="7. Contact">
                <p>
                    {company.name}
                    <br />
                    {company.address}
                    <br />
                    {sms.help_email} &middot; {sms.help_phone}
                </p>
            </Section>
        </LegalLayout>
    );
}
