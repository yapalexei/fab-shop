import LegalLayout, { List, Section } from '@/Layouts/LegalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    company: {
        name: string;
        address: string;
        contact_email: string;
        governing_law: string;
        venue: string;
    };
    appName: string;
    effectiveDate: string;
}

export default function Terms({ company, appName, effectiveDate }: Props) {
    const link = 'font-medium text-indigo-600 underline hover:text-indigo-900';

    return (
        <LegalLayout title="Terms of Service" effectiveDate={effectiveDate}>
            <p>
                These Terms of Service (the "Terms") are an agreement between
                you and {company.name} ("{appName}", "we", "us") that governs
                your use of {appName}'s website and software for quoting, job
                tracking and invoicing (the "Service"). By creating an account
                or using the Service you agree to these Terms. If you are
                accepting on behalf of a business, you confirm you have the
                authority to bind it, and "you" means that business.
            </p>

            <Section title="1. Who can use the Service">
                <p>
                    The Service is for business use by fabrication, welding and
                    similar shops. You must be at least 18 years old and able to
                    form a binding contract. You may not use the Service if we
                    have previously suspended or removed your account for a
                    violation of these Terms.
                </p>
            </Section>

            <Section title="2. Accounts, shops and team members">
                <List>
                    <li>
                        You must give accurate registration information and keep
                        it current. You are responsible for keeping your login
                        credentials secret and for all activity under your
                        account.
                    </li>
                    <li>
                        Each shop has one owner. The owner (and anyone they give
                        permission to) can invite team members and decide what
                        each person can see and do. The owner is responsible for
                        the people they invite and for the access they grant.
                    </li>
                    <li>
                        Tell us promptly at {company.contact_email} if you
                        believe your account has been accessed without
                        permission.
                    </li>
                </List>
            </Section>

            <Section title="3. The Service">
                <p>
                    We provide the Service as described on our website and may
                    improve, change or remove features over time. Which features
                    you can use depends on your plan and any add-ons you have
                    subscribed to. We may offer new features on a trial or beta
                    basis; those are provided without commitment and may change
                    or end at any time.
                </p>
            </Section>

            <Section title="4. Free trial, plans, add-ons and fees">
                <List>
                    <li>
                        <strong>Free trial.</strong> New shops may receive a
                        free trial of some or all features for the period shown
                        in the app. When a trial ends and you have not
                        subscribed to a plan or add-on that includes a feature,
                        that feature becomes read-only: you can still view the
                        data you created, but you cannot add to or change it
                        until you subscribe.
                    </li>
                    <li>
                        <strong>Paid plans and add-ons.</strong> Where paid
                        plans and add-ons are offered, fees are shown in the app
                        before you subscribe, are billed in advance for the
                        billing period you choose (monthly or annual), and renew
                        automatically until you cancel. All plan and add-on
                        subscriptions on your shop share one billing date.
                    </li>
                    <li>
                        <strong>Charged immediately.</strong> When you add a
                        paid plan or add-on part-way through a billing period,
                        you are charged right away for the portion of the period
                        that remains (prorated), and the full price at each
                        renewal after that. The amount and renewal date are
                        shown to you before you confirm.
                    </li>
                    <li>
                        <strong>Cancelling; no refunds.</strong> You can cancel
                        an add-on or plan at any time. Cancellation takes effect
                        at the end of the billing period you have already paid
                        for, and you keep access until then. Except where the
                        law requires otherwise, fees are non-refundable and we
                        do not give credits for partial periods or unused
                        features.
                    </li>
                    <li>
                        <strong>Changes and taxes.</strong> We may change prices
                        or plan contents with at least 30 days' notice; changes
                        apply from your next renewal. Fees exclude taxes, which
                        you are responsible for unless we state otherwise.
                    </li>
                    <li>
                        <strong>Non-payment.</strong> If a payment fails we may
                        retry it, notify you, and restrict or suspend paid
                        features until it is resolved.
                    </li>
                </List>
            </Section>

            <Section title="5. Your data">
                <List>
                    <li>
                        <strong>You own it.</strong> You keep all rights to the
                        information you put into the Service, including your
                        customers' details, quotes, jobs, pricing, materials,
                        photos, team information and qualification records
                        ("Your Data").
                    </li>
                    <li>
                        <strong>Our permission to use it.</strong> You give us a
                        limited right to host, process, copy, display and
                        transmit Your Data only as needed to provide and secure
                        the Service, to support you, and as described in our{' '}
                        <Link href={route('legal.privacy')} className={link}>
                            Privacy Policy
                        </Link>
                        .
                    </li>
                    <li>
                        <strong>Your responsibility.</strong> You are
                        responsible for Your Data and for having the right to
                        collect it and use it in the Service - including the
                        right to store and contact the customers, employees and
                        suppliers whose details you enter. Where applicable we
                        act as your service provider (processor) for personal
                        information about those people.
                    </li>
                    <li>
                        <strong>Export and deletion.</strong> You can export
                        much of Your Data from the app, and you can ask us to
                        delete your account. After an account is closed we
                        delete or anonymize Your Data within a reasonable
                        period, except for what we must keep for legal, tax,
                        security or audit reasons (for example, records of the
                        agreements and consents described below).
                    </li>
                </List>
            </Section>

            <Section title="6. Acceptable use">
                <p>You agree not to, and not to let anyone else:</p>
                <List>
                    <li>
                        use the Service in violation of any law or anyone's
                        rights, or to send spam or unlawful, deceptive or
                        harassing messages;
                    </li>
                    <li>
                        upload malware, or interfere with or try to bypass the
                        security, limits or availability of the Service;
                    </li>
                    <li>
                        probe, scan or test the Service's security without our
                        written permission, or access another shop's data;
                    </li>
                    <li>
                        reverse engineer or copy the Service, or use it to build
                        a competing product;
                    </li>
                    <li>
                        resell or share access to the Service outside your shop,
                        or exceed the user limits of your plan.
                    </li>
                </List>
            </Section>

            <Section title="7. Email and text messages">
                <List>
                    <li>
                        <strong>Messages about the Service.</strong> We send
                        emails needed to run your account, such as address
                        verification, team invitations, trial and billing
                        notices and security alerts. You can't opt out of these
                        while you have an account.
                    </li>
                    <li>
                        <strong>Messages you send through the Service.</strong>{' '}
                        When you use the Service to email a quote or other
                        message to your customer or anyone else, you are the
                        sender. You are responsible for the content and for
                        having the right to contact that person, and for
                        following the email, telemarketing and privacy laws that
                        apply to you (such as CAN-SPAM). We send the message at
                        your direction and are not a party to your relationship
                        with the recipient.
                    </li>
                    <li>
                        <strong>Text messages (SMS).</strong> We do not
                        currently send text messages. If we add SMS features, we
                        will send text messages only to people who have agreed
                        to receive them, using the separate opt-in and terms on
                        our{' '}
                        <Link href={route('legal.sms')} className={link}>
                            SMS Terms
                        </Link>{' '}
                        page, which form part of these Terms. If you use SMS
                        features to message your own customers, you must obtain
                        and keep each recipient's consent as those terms
                        require, honor opt-outs (such as STOP) immediately, and
                        not use the Service to send marketing messages to anyone
                        who has not agreed. We may keep records of consents and
                        opt-outs and may block messages we believe violate these
                        rules or carrier requirements.
                    </li>
                    <li>
                        <strong>Consent is not a condition of purchase.</strong>{' '}
                        Agreeing to receive text messages is optional and is not
                        required to buy or use the Service.
                    </li>
                </List>
            </Section>

            <Section title="8. Integrations">
                <p>
                    The Service can connect to third-party services you choose,
                    such as QuickBooks Online. When you connect one you
                    authorize us to exchange the data needed for that feature
                    (for example, to create invoices and customers in your
                    QuickBooks company). The third party's own terms and privacy
                    practices apply to that connection, we do not control that
                    service, and we are not responsible for it. You can
                    disconnect an integration at any time in the app or in the
                    third party's settings.
                </p>
            </Section>

            <Section title="9. Estimates, pricing and professional responsibility">
                <p>
                    The Service helps you build quotes, track jobs and material
                    use, and keep records, using the prices, rates, markups and
                    other figures you enter. We do not verify those inputs or
                    the results. You are solely responsible for the prices you
                    quote, the work you perform, tax and invoicing, and for
                    checking every figure before you rely on or send it. Nothing
                    in the Service is engineering, legal, tax, accounting or
                    safety advice. Features such as qualification and
                    certification records only store what you enter; they do not
                    certify anyone or confirm that work meets any code,
                    standard, contract or regulation. You remain responsible for
                    that compliance.
                </p>
            </Section>

            <Section title="10. Our intellectual property; feedback">
                <p>
                    We and our licensors own the Service, including its
                    software, design, text and trademarks. These Terms give you
                    only a limited, non-exclusive, non-transferable right to use
                    the Service for your internal business while they are in
                    effect. If you give us feedback or suggestions, we may use
                    them without restriction or payment.
                </p>
            </Section>

            <Section title="11. Privacy">
                <p>
                    Our{' '}
                    <Link href={route('legal.privacy')} className={link}>
                        Privacy Policy
                    </Link>{' '}
                    explains what we collect and how we use it. It is part of
                    these Terms.
                </p>
            </Section>

            <Section title="12. Records of your agreement">
                <p>
                    When you accept these Terms, the Privacy Policy, or a
                    request to receive text messages, we keep a record of what
                    you agreed to: the document and its version, the wording you
                    saw, the time, your account, and technical details such as
                    your IP address and browser. We keep these records for as
                    long as we need them to show that consent was given or
                    withdrawn, including after an account is closed.
                </p>
            </Section>

            <Section title="13. Availability and support">
                <p>
                    We work to keep the Service available but do not promise
                    that it will be uninterrupted, error-free or free of data
                    loss. We may perform maintenance, and parts of the Service
                    may be unavailable from time to time. Please keep your own
                    copies of information that is critical to your business.
                    Support is available at {company.contact_email}.
                </p>
            </Section>

            <Section title="14. Suspension and termination">
                <p>
                    You may stop using the Service and close your account at any
                    time. We may suspend or end your access, immediately if
                    necessary, if you breach these Terms, fail to pay, create
                    risk or legal exposure for us or others, or if we stop
                    providing the Service. If we end your access without cause,
                    we will give you reasonable notice and a chance to export
                    Your Data. Sections that by their nature should survive
                    termination will survive, including those on fees owed, Your
                    Data, disclaimers, liability, indemnity and disputes.
                </p>
            </Section>

            <Section title="15. Disclaimers">
                <p className="uppercase">
                    The Service is provided "as is" and "as available". To the
                    fullest extent permitted by law, we disclaim all warranties,
                    express or implied, including warranties of merchantability,
                    fitness for a particular purpose, accuracy and
                    non-infringement. We do not warrant that the Service will
                    meet your requirements or that results from it, including
                    any quote, cost or margin figure, will be accurate or
                    complete.
                </p>
            </Section>

            <Section title="16. Limitation of liability">
                <p className="uppercase">
                    To the fullest extent permitted by law, we will not be
                    liable for any indirect, incidental, special, consequential
                    or punitive damages, or for lost profits, revenue, data or
                    goodwill, arising out of or relating to the Service or these
                    Terms, even if advised of the possibility. Our total
                    liability for all claims relating to the Service will not
                    exceed the greater of the amount you paid us in the 12
                    months before the claim arose and one hundred U.S. dollars
                    ($100). Some jurisdictions do not allow certain limits, so
                    parts of this section may not apply to you.
                </p>
            </Section>

            <Section title="17. Indemnity">
                <p>
                    You will defend and indemnify us and our officers, employees
                    and contractors against claims, damages and costs (including
                    reasonable legal fees) arising from Your Data, your use of
                    the Service in breach of these Terms, messages you send
                    through the Service, or work you perform or quote for your
                    customers.
                </p>
            </Section>

            <Section title="18. Governing law and disputes">
                <p>
                    These Terms are governed by the laws of{' '}
                    {company.governing_law}, without regard to conflict-of-law
                    rules. Before filing a claim, each party agrees to try to
                    resolve the dispute informally by contacting the other (for
                    us, at {company.contact_email}) and allowing 30 days.
                    Subject to that, any dispute will be brought exclusively in
                    the state or federal courts located in {company.venue}, and
                    each party consents to their jurisdiction and venue.
                </p>
            </Section>

            <Section title="19. Changes to these Terms">
                <p>
                    We may update these Terms. For material changes we will
                    notify you by email or in the app before they take effect,
                    and the effective date above will change. If you keep using
                    the Service after the effective date, you accept the updated
                    Terms; if you do not agree, you should stop using the
                    Service and close your account.
                </p>
            </Section>

            <Section title="20. General">
                <p>
                    These Terms (with the Privacy Policy and SMS Terms) are the
                    entire agreement between you and us about the Service. If a
                    provision is unenforceable, the rest remains in effect. Our
                    not enforcing a provision is not a waiver. You may not
                    assign these Terms without our consent; we may assign them
                    in a merger, acquisition or sale of assets. Neither party is
                    liable for delay caused by events beyond its reasonable
                    control. Notices to you may be sent to the email on your
                    account or shown in the app; notices to us go to{' '}
                    {company.contact_email} or {company.address}.
                </p>
            </Section>

            <Section title="21. Contact">
                <p>
                    {company.name}
                    <br />
                    {company.address}
                    <br />
                    {company.contact_email}
                </p>
            </Section>
        </LegalLayout>
    );
}
