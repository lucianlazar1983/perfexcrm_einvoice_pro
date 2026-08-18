<a href='https://ko-fi.com/W7W61NW50T' target='_blank'><img height='36' style='border:0px;height:36px;' src='https://storage.ko-fi.com/cdn/kofi6.png?v=6' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>


# E-Invoice Pro for PerfexCRM (Romania)

A minimalist, fully configurable module for PerfexCRM designed to generate UBL XML files compliant with the **Romanian e-factura national system (RO e-factura)**.

This module was built from the ground up to be lightweight, stable, and easy to configure, providing a simple one-click solution for generating valid e-invoices.

---

## Features

*  **Valid XML Generation:** Creates UBL XML files that pass the official ANAF validator.
*  **One-Click Download:** Adds a simple "e-Invoice" button directly to the invoice preview page.
*  **Fully Configurable:** All company-specific data (Registration Number, Bank Info, Invoice Notes) is managed through a dedicated settings page, requiring no code changes.
*  **Multi-language Support:** The module's user interface is available in both English and Romanian, automatically adapting to the user's language preference.
*  **Selectable XML Language:** Allows you to choose the output language for the XML notes, independent of the admin interface language.

---

## Installation

1.  Download the module's `.zip` file from the repository.
2.  Navigate to **Setup -> Modules** in your PerfexCRM admin area.
3.  Click the **Install Module** button and upload the `.zip` file.
4.  After the upload is complete, find **E-Invoice Pro** in the module list and click the **Activate** button. The necessary database settings will be created automatically.

---

## Configuration

All module settings are located in a dedicated panel, ensuring a clean integration with PerfexCRM.

**How to find the settings page:**
1.  Navigate to **Setup -> Settings**.
2.  Click on the **Finance** tab.
3.  You will see a new sub-tab called **E-Invoice Pro**. Click on it.



### Configuration Fields Explained

Here is an explanation of each setting and why it's needed for the e-factura XML file.

#### XML Output Language
* **XML Generation Language:** This dropdown controls the language of the notes included in the final XML file. You can have your PerfexCRM interface in English but generate an XML with notes in Romanian, or vice-versa.

#### Company Details
* **Company Registration Number:** This field is **required** by the e-factura standard and is configured **directly on this settings page**. You should enter your company's registration number from the Trade Register (e.g., `J12/1234/1999`). This value populates the following tag in the XML:
    ```xml
    <cac:PartyLegalEntity>
        <cbc:CompanyID>J12/1234/1999</cbc:CompanyID>
    </cac:PartyLegalEntity>
    ```
    *Note: This is different from the `VAT Number`, which is configured in the standard PerfexCRM settings (`Setup -> Settings -> Company Information`).*

* **Social Capital (numeric value only):** Enter the numeric value of your company's social capital (e.g., `200`). The module will automatically prepend the text "Capital social: " to the value in the XML file.

#### Bank Details
* **IBAN Account:** Enter the IBAN where you wish to receive payments for the invoice.
* **Bank Name:** Enter the name of your bank. These fields populate the `<cac:PaymentMeans>` block in the XML.

#### Invoice Notes
*   **manage Custom Values:** Next to each note dropdown, you have the ability to click "Manage Values". This opens a panel where you can:
    *   **Add New Notes:** Define your own custom text for notes.
    *   **Delete Existing Notes:** Remove notes you no longer use (except for the built-in defaults).
    *   **Selection:** The dropdowns will be automatically updated with your custom values.
*   **Selectable XML Language:** The text of the *default* notes is automatically translated based on the **XML Generation Language** you selected. Custom notes added by you will appear exactly as typed.
*   To omit a note, simply select **"None"**.

After configuring all the fields, click the **Save Settings** button. The module is now ready to use!
