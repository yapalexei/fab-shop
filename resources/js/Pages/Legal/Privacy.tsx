import LegalLayout, { List, Section } from '@/Layouts/LegalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    company: {
        name: string;
        address: string;
        contact_email: string;
        privacy_email: string;
    };
    appName: string;
    effectiveDate: string;
}

export default function Privacy({ company, appName, effectiveDate }: Props) {
    const link = 'font-medium text-indigo-600 underline hover:underline';

    return (
        <LegalLayout title="Privacy Policy" effectiveDate={effectiveDate}>
            <p>
                This Privacy Policy explains how {company.name} ("{appName}",
                "we", "us") collects, uses, shares and protects personal
                information when you use our website and our software for
                quoting, job tracking and invoicing (the "Service"). It should
                be read with our{' '}
                <Link href={route('legal.terms')} className={link}>
                    Terms of Service
                </Link>
                .
            </p>

            <Section title="1. Two kinds of information, two roles">
                <List>
                    <li>
                        <strong>Account information</strong> about you and your
                        team - we decide how it is used and are the "business"
                        or "controller" for it.
                    </li>
                    <li>
                        <strong>Shop data</strong> that you enter about your own
                        customers, suppliers, employees and jobs. You decide
                        what goes in and why; we handle it only to provide the
                        Service to you, acting as your "service provider" or
                        "processor". If you are a customer of a shop that uses{' '}
                        {appName} and have a question or request about your
                        information, please contact that shop first.
                    </li>
                </List>
            </Section>

            <Section title="2. Information we collect">
                <p>
                    <strong>Information you give us</strong>
                </p>
                <List>
                    <li>
                        Account details: your name, email address and password
                        (stored only as a one-way hash).
                    </li>
                    <li>
                        Shop details: shop name, time zone, plan and settings,
                        and the names, emails and roles of people you invite.
                    </li>
                    <li>
                        Shop data you enter: customer and supplier contact
                        details (names, emails, phone numbers, addresses),
                        quotes, jobs, pricing, materials, equipment and tooling,
                        job photos and notes, hourly rates, and qualification or
                        certification records for your team.
                    </li>
                    <li>Messages you send us, such as support requests.</li>
                </List>

                <p>
                    <strong>Information collected automatically</strong>
                </p>
                <List>
                    <li>
                        Usage and activity: actions in the app (for example,
                        changes to a quote or job and who made them), timestamps
                        and feature use.
                    </li>
                    <li>
                        Device and log data: IP address, browser type, pages
                        requested, and error and security logs.
                    </li>
                    <li>Cookies and similar technology (see section 7).</li>
                </List>

                <p>
                    <strong>Information from services you connect</strong>
                </p>
                <List>
                    <li>
                        If you connect QuickBooks Online, we receive and store
                        the authorization tokens needed to act on your behalf,
                        and exchange customer and invoice information with your
                        QuickBooks company.
                    </li>
                </List>

                <p>
                    <strong>Records of your agreements</strong>
                </p>
                <List>
                    <li>
                        When you accept our Terms or Privacy Policy, or agree
                        (or decline) to receive text messages, we store the
                        document and version, the wording shown, your account,
                        your email or mobile number, the time, and your IP
                        address and browser. We keep these records to prove what
                        was agreed.
                    </li>
                </List>

                <p>
                    <strong>Payment information</strong>
                </p>
                <List>
                    <li>
                        Where paid plans are offered, payment is handled by a
                        third-party payment processor. We do not store full card
                        numbers; we receive limited details such as card brand,
                        last four digits, billing status and invoices.
                    </li>
                </List>
            </Section>

            <Section title="3. How we use information">
                <List>
                    <li>
                        To provide, operate, maintain and secure the Service.
                    </li>
                    <li>
                        To create and manage accounts, authenticate you and
                        apply your plan, trial and subscriptions.
                    </li>
                    <li>
                        To send service messages: verification, invitations,
                        quotes you ask us to send, trial and billing notices,
                        and security alerts.
                    </li>
                    <li>
                        To provide support, and to investigate and prevent
                        fraud, abuse and security incidents.
                    </li>
                    <li>
                        To understand how the Service is used and to improve it.
                    </li>
                    <li>
                        To keep records of consent and comply with legal,
                        accounting, tax and carrier requirements.
                    </li>
                </List>
                <p>
                    We do not use shop data to advertise to you or to build
                    advertising profiles, and we do not sell it.
                </p>
            </Section>

            <Section title="4. How we share information">
                <p>
                    <strong>We do not sell personal information</strong>, and we
                    do not share it for cross-context behavioral advertising. We
                    share it only:
                </p>
                <List>
                    <li>
                        <strong>With service providers</strong> that help us run
                        the Service under contract and only for our purposes -
                        for example cloud hosting and storage, email delivery
                        and error and performance monitoring. Our current
                        providers are:
                        <List>
                            <li>
                                <strong>Railway</strong> - application hosting,
                                database, queue and file storage.
                            </li>
                            <li>
                                <strong>Resend</strong> - delivery of email the
                                Service sends (verification, invitations,
                                notices).
                            </li>
                        </List>
                        Questions about providers can go to{' '}
                        {company.privacy_email}.
                    </li>
                    <li>
                        <strong>With integrations you enable</strong>, such as
                        Intuit (QuickBooks Online), and only the data needed for
                        that feature.
                    </li>
                    <li>
                        <strong>With an AI provider your shop chooses.</strong>{' '}
                        If the shop owner turns on the AI assistant and adds
                        their own key from Anthropic or OpenAI, the text a
                        person asks the assistant to work on (for example a
                        pasted customer email) is sent to that provider under
                        the shop's own account with them. We do not send it
                        anywhere else, and we do not keep the text or the
                        answer in our logs.
                    </li>
                    <li>
                        <strong>Within your shop</strong>: other people in your
                        shop can see shop data according to the access the owner
                        has granted them.
                    </li>
                    <li>
                        <strong>For legal reasons</strong>, to comply with law
                        or valid legal process, to enforce our terms, or to
                        protect the rights, safety and security of our users,
                        the public or us.
                    </li>
                    <li>
                        <strong>In a business transfer</strong> such as a
                        merger, acquisition or sale of assets, with notice if
                        your information becomes subject to a different policy.
                    </li>
                    <li>
                        <strong>With your direction or consent.</strong>
                    </li>
                </List>
            </Section>

            <Section title="5. Email and text messages">
                <List>
                    <li>
                        <strong>Email.</strong> We send service emails as
                        described above; you cannot opt out of those while you
                        have an account. If we send marketing emails, each will
                        include an unsubscribe link and we will honor it. When
                        you send a quote or message to your own customer through
                        the Service, it is sent on your behalf and at your
                        direction.
                    </li>
                    <li>
                        <strong>Text messages (SMS).</strong> We do not
                        currently send text messages. If we do, we will send
                        them only to people who have agreed to receive them,
                        under our{' '}
                        <Link href={route('legal.sms')} className={link}>
                            SMS Terms
                        </Link>
                        . Message and data rates may apply, and you can reply
                        STOP at any time to opt out.
                    </li>
                    <li>
                        <strong>
                            Mobile information is never shared for marketing.
                        </strong>{' '}
                        No mobile information will be shared with third parties
                        or affiliates for marketing or promotional purposes.
                        Information sharing to subcontractors in support of
                        services, such as customer care or message delivery, is
                        permitted. All other categories exclude text messaging
                        originator opt-in data and consent; this information
                        will not be shared with any third parties.
                    </li>
                </List>
            </Section>

            <Section title="6. How long we keep information">
                <p>
                    We keep account and shop data while your account is open.
                    After an account is closed we delete or anonymize it within
                    a reasonable period, except where we must or may keep it for
                    legal, tax, security, fraud-prevention or audit purposes.
                    Records of consent and agreement are kept for as long as we
                    need them to demonstrate what was agreed, including after
                    account closure. Backups are overwritten on a rolling
                    schedule.
                </p>
            </Section>

            <Section title="7. Cookies and local storage">
                <p>
                    We use only what the Service needs to work: a session cookie
                    that keeps you signed in, a security (CSRF) token, and
                    preferences and drafts stored in your browser (such as light
                    or dark mode, a notice you dismissed, and an unsaved quote
                    draft so you do not lose work). We do not use advertising or
                    cross-site tracking cookies. You can clear this data in your
                    browser at any time, though you may be signed out and lose
                    unsaved drafts. We do not currently respond to "Do Not
                    Track" signals because we do not track you across other
                    sites.
                </p>
            </Section>

            <Section title="8. Security">
                <p>
                    We use reasonable administrative, technical and
                    organizational measures to protect information, including
                    encrypted connections, hashed passwords, access controls
                    that separate each shop's data from other shops, and
                    role-based permissions within a shop, optional two-factor
                    sign-in, and a log of sign-ins and changes to access. No system is perfectly
                    secure, so we cannot guarantee absolute security. Please use
                    a strong, unique password and tell us right away if you
                    suspect unauthorized access.
                </p>
            </Section>

            <Section title="9. Your rights and choices">
                <p>
                    Depending on where you live, you may have the right to
                    access, correct, delete or receive a copy of your personal
                    information, to object to or restrict certain uses, and to
                    withdraw consent. Residents of California and several other
                    U.S. states have rights under state privacy laws, including
                    to know what we collect, to delete it, to correct it, and
                    not to be discriminated against for exercising these rights.
                    We do not sell or share personal information as those laws
                    define them.
                </p>
                <List>
                    <li>
                        You can update much of your information in the app, and
                        close your account to have it deleted.
                    </li>
                    <li>
                        To make a request, email {company.privacy_email}. We may
                        need to verify your identity, and an authorized agent
                        may submit a request for you with proof of
                        authorization. We respond within the time the law
                        requires, and if we deny a request you may ask us to
                        reconsider it.
                    </li>
                    <li>
                        Requests about information a shop entered about you (for
                        example as its customer) should go to that shop; we will
                        help it respond.
                    </li>
                    <li>
                        To stop text messages reply STOP; to stop marketing
                        emails use the unsubscribe link.
                    </li>
                </List>
            </Section>

            <Section title="10. International use and transfers">
                <p>
                    The Service is operated from the United States and intended
                    for shops in the United States. If you use it from another
                    country, your information will be transferred to and
                    processed in the United States, which may have different
                    data protection laws than your country.
                </p>
            </Section>

            <Section title="11. Children">
                <p>
                    The Service is for business use and is not directed to
                    anyone under 18. We do not knowingly collect personal
                    information from children. If you believe a child has given
                    us information, contact {company.privacy_email} and we will
                    delete it.
                </p>
            </Section>

            <Section title="12. Changes to this policy">
                <p>
                    We may update this policy. For material changes we will
                    notify you by email or in the app before they take effect,
                    and update the effective date above.
                </p>
            </Section>

            <Section title="13. Contact us">
                <p>
                    {company.name}
                    <br />
                    {company.address}
                    <br />
                    Privacy requests: {company.privacy_email}
                    <br />
                    General: {company.contact_email}
                </p>
            </Section>
        </LegalLayout>
    );
}
