using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Reflection;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;

[assembly: AssemblyTitle("Instalador AnferaltaDocs")]
[assembly: AssemblyDescription("Instalação Windows de raiz com IIS, PHP, MySQL e HTTPS empresarial")]
[assembly: AssemblyVersion("2.0.0.0")]

namespace AnferaltaDocsSetup
{
    internal static class Program
    {
        [STAThread]
        private static int Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            try
            {
                if (args.Length == 1 && args[0].StartsWith("--self-test=", StringComparison.Ordinal)) return SelfTest.Run(args[0].Substring(12));
                if (args.Length == 1 && args[0].StartsWith("--bootstrap-self-test=", StringComparison.Ordinal)) return BootstrapSelfTest.Run(args[0].Substring(22));
                if (args.Length == 1 && args[0] == "--prepared-server") Application.Run(new Wizard());
                else Application.Run(new CleanMachineWizard());
                return 0;
            }
            catch (Exception)
            {
                if (args.Length == 1 && (args[0].StartsWith("--self-test=", StringComparison.Ordinal) || args[0].StartsWith("--bootstrap-self-test=", StringComparison.Ordinal))) return 1;
                MessageBox.Show("Não foi possível iniciar o instalador. Confirme as permissões e o .NET Framework 4.8 do Windows.", "AnferaltaDocs", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return 1;
            }
        }
    }

    internal static class Package
    {
        internal static string Hash(Stream stream)
        {
            using (var sha = SHA256.Create()) return BitConverter.ToString(sha.ComputeHash(stream)).Replace("-", "").ToLowerInvariant();
        }

        internal static string HashFile(string path) { using (var input = File.OpenRead(path)) return Hash(input); }

        internal static string SafePath(string root, string relative)
        {
            if (String.IsNullOrEmpty(relative) || relative.Contains("\\") || relative.Contains(":") || relative.StartsWith("/")) throw new InvalidDataException("Caminho inválido no pacote.");
            foreach (string part in relative.Split('/'))
            {
                if (part.Length == 0 || part == "." || part == ".." || part.EndsWith(".") || part.EndsWith(" ") || part.IndexOfAny(Path.GetInvalidFileNameChars()) >= 0 || Regex.IsMatch(part, @"^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.|$)", RegexOptions.IgnoreCase)) throw new InvalidDataException("Caminho inválido no pacote.");
            }
            string prefix = Path.GetFullPath(root).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
            string target = Path.GetFullPath(Path.Combine(root, relative.Replace('/', Path.DirectorySeparatorChar)));
            if (!target.StartsWith(prefix, StringComparison.OrdinalIgnoreCase)) throw new InvalidDataException("Ficheiro fora do destino.");
            return target;
        }

        internal static void CheckParents(string path)
        {
            for (var item = new DirectoryInfo(Path.GetFullPath(path)); item != null; item = item.Parent)
                if (item.Exists && (item.Attributes & FileAttributes.ReparsePoint) != 0) throw new IOException("Escolha um destino sem ligações simbólicas ou junctions.");
        }

        internal static void Extract(string destination, Action<string> log, bool resumePartial = false)
        {
            string root = Path.GetFullPath(destination);
            CheckParents(root);
            if (Directory.Exists(root) && Directory.EnumerateFileSystemEntries(root).Any() && !resumePartial) throw new IOException("A pasta de destino deve estar vazia.");
            if (resumePartial && File.Exists(Path.Combine(root, ".env"))) throw new IOException("Não é possível repetir a extração sobre uma configuração publicada.");
            Directory.CreateDirectory(root);
            using (Stream payload = Assembly.GetExecutingAssembly().GetManifestResourceStream("AnferaltaDocs.Payload.zip"))
            {
                if (payload == null) throw new InvalidDataException("Pacote de aplicação em falta.");
                using (var archive = new ZipArchive(payload, ZipArchiveMode.Read))
                {
                    var metadata = archive.GetEntry("setup-manifest.json");
                    if (metadata == null || metadata.Length > 8 * 1024 * 1024) throw new InvalidDataException("Manifesto inválido.");
                    Dictionary<string, string> manifest;
                    using (var reader = new StreamReader(metadata.Open(), Encoding.UTF8)) manifest = new JavaScriptSerializer().Deserialize<Dictionary<string, string>>(reader.ReadToEnd());
                    if (archive.Entries.Count != manifest.Count + 1) throw new InvalidDataException("Conteúdo inesperado no pacote.");
                    if (resumePartial && Directory.EnumerateFiles(root, "*", SearchOption.AllDirectories).Any(file => !manifest.ContainsKey(file.Substring(root.TrimEnd('\\').Length + 1).Replace('\\', '/')))) throw new IOException("A extração incompleta contém ficheiros externos ao pacote; requer revisão.");
                    var names = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                    var directories = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                    int count = 0;
                    foreach (var entry in archive.Entries)
                    {
                        if (!names.Add(entry.FullName)) throw new InvalidDataException("Ficheiro duplicado no pacote.");
                        string target = SafePath(root, entry.FullName);
                        if (entry.FullName == "setup-manifest.json") continue;
                        string expected;
                        if (!manifest.TryGetValue(entry.FullName, out expected) || !Regex.IsMatch(expected, "^[a-f0-9]{64}$")) throw new InvalidDataException("Manifesto incompleto.");
                        string parent = Path.GetDirectoryName(target);
                        CheckParents(target);
                        if (directories.Add(parent)) Directory.CreateDirectory(parent);
                        if (!File.Exists(target)) { using (var input = entry.Open()) using (var output = new FileStream(target, FileMode.CreateNew, FileAccess.Write, FileShare.None)) input.CopyTo(output); }
                        if (!String.Equals(HashFile(target), expected, StringComparison.Ordinal)) throw new InvalidDataException("O pacote não passou na verificação de integridade.");
                        count++;
                        if (count % 400 == 0) log("A extrair e verificar a aplicação (" + count + " ficheiros)...");
                    }
                    File.WriteAllText(Path.Combine(root, ".setup-package.json"), new JavaScriptSerializer().Serialize(manifest), new UTF8Encoding(false));
                    log("Aplicação e bibliotecas extraídas; hashes verificados.");
                }
            }
        }

        internal static void VerifyExisting(string root, bool allowCompletedBootstrap = false)
        {
            CheckParents(root);
            string marker = Path.Combine(root, ".setup-package.json");
            if (!File.Exists(marker)) throw new IOException("A pasta contém ficheiros e não pertence a este instalador. Escolha outra pasta vazia.");
            var manifest = new JavaScriptSerializer().Deserialize<Dictionary<string, string>>(File.ReadAllText(marker, Encoding.UTF8));
            foreach (var item in manifest)
            {
                string file = SafePath(root, item.Key);
                if (!File.Exists(file) || !String.Equals(HashFile(file), item.Value, StringComparison.Ordinal)) throw new IOException("O código extraído foi alterado. Use uma cópia limpa ou o procedimento de manutenção.");
            }
            if (!allowCompletedBootstrap && File.Exists(Path.Combine(root, ".env")) && !File.Exists(Path.Combine(root, "storage", ".installation-incomplete"))) throw new IOException("Esta aplicação já está instalada. O assistente não substitui instalações existentes.");
        }

        internal static void SecureDirectory(string path)
        {
            Directory.CreateDirectory(path);
            var permissions = new DirectorySecurity();
            permissions.SetAccessRuleProtection(true, false);
            var inherit = InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit;
            permissions.AddAccessRule(new FileSystemAccessRule(WindowsIdentity.GetCurrent().User, FileSystemRights.FullControl, inherit, PropagationFlags.None, AccessControlType.Allow));
            permissions.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, inherit, PropagationFlags.None, AccessControlType.Allow));
            new DirectoryInfo(path).SetAccessControl(permissions);
        }

        internal static string QuoteArgument(string value)
        {
            var result = new StringBuilder("\""); int slashes = 0;
            foreach (char character in value)
            {
                if (character == '\\') { slashes++; continue; }
                if (character == '"') result.Append('\\', slashes * 2 + 1).Append('"');
                else result.Append('\\', slashes).Append(character);
                slashes = 0;
            }
            return result.Append('\\', slashes * 2).Append('"').ToString();
        }
    }

    internal sealed class Wizard : Form
    {
        private readonly TabControl pages = new TabControl();
        private readonly Button next = new Button(), back = new Button();
        private readonly TextBox target = new TextBox(), php = new TextBox(), url = new TextBox(), databaseHost = new TextBox(), databasePort = new TextBox(), databaseName = new TextBox(), databaseUser = new TextBox(), databasePassword = new TextBox(), administrator = new TextBox(), administratorName = new TextBox(), administratorPassword = new TextBox(), administratorConfirmation = new TextBox();
        private readonly CheckBox enableMail = new CheckBox();
        private readonly TextBox mailHost = new TextBox(), mailPort = new TextBox(), mailUser = new TextBox(), mailPassword = new TextBox(), mailFrom = new TextBox(), mailCa = new TextBox();
        private readonly ComboBox mailEncryption = new ComboBox();
        private readonly TextBox summary = new TextBox(), log = new TextBox();
        private readonly ProgressBar progress = new ProgressBar();
        private string[] secretValues = new string[0];
        private bool busy, completed;

        internal Wizard()
        {
            Text = "AnferaltaDocs — Assistente de instalação";
            ClientSize = new Size(840, 650); MinimumSize = new Size(800, 688); StartPosition = FormStartPosition.CenterScreen;
            Font = new Font("Segoe UI", 10F); BackColor = Color.White;
            var header = new Label { Text = "AnferaltaDocs\nInstalação no servidor da entidade", Dock = DockStyle.Top, Height = 83, Padding = new Padding(24, 12, 0, 0), Font = new Font("Segoe UI", 16F, FontStyle.Bold), BackColor = Color.FromArgb(24, 51, 78), ForeColor = Color.White };
            Controls.Add(header);
            pages.Location = new Point(20, 98); pages.Size = new Size(800, 480); pages.Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right;
            Controls.Add(pages);
            back.Text = "Anterior"; back.Location = new Point(566, 598); back.Size = new Size(110, 34); back.Anchor = AnchorStyles.Right | AnchorStyles.Bottom; back.Click += delegate { if (!busy && pages.SelectedIndex > 0) pages.SelectedIndex--; };
            next.Text = "Seguinte"; next.Location = new Point(686, 598); next.Size = new Size(130, 34); next.Anchor = AnchorStyles.Right | AnchorStyles.Bottom; next.Click += Next;
            Controls.Add(back); Controls.Add(next);
            AddDestination(); AddDatabase(); AddAdministrator(); AddMail(); AddExecution();
            pages.SelectedIndexChanged += delegate { if (!busy) { back.Enabled = pages.SelectedIndex > 0; next.Text = completed ? "Fechar" : (pages.SelectedIndex == 4 ? "Instalar" : "Seguinte"); if (pages.SelectedIndex == 4) UpdateSummary(); } };
            back.Enabled = false;
            FormClosing += delegate(object sender, FormClosingEventArgs e) { if (busy) { e.Cancel = true; MessageBox.Show(this, "Aguarde a conclusão da operação. O instalador está a proteger a criação da base de dados.", "Instalação em curso"); } };
        }

        private TableLayoutPanel Page(string title)
        {
            var page = new TabPage(title) { BackColor = Color.White, Padding = new Padding(16), AutoScroll = true };
            var table = new TableLayoutPanel { Dock = DockStyle.Top, AutoSize = true, ColumnCount = 2, Padding = new Padding(5) };
            table.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 235)); table.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
            page.Controls.Add(table); pages.TabPages.Add(page); return table;
        }

        private static void Note(TableLayoutPanel table, string text)
        {
            var label = new Label { Text = text, AutoSize = true, MaximumSize = new Size(720, 0), Margin = new Padding(0, 5, 0, 20) };
            int row = table.RowCount++; table.Controls.Add(label, 0, row); table.SetColumnSpan(label, 2);
        }

        private static void Field(TableLayoutPanel table, string title, Control input)
        {
            int row = table.RowCount++;
            table.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            table.Controls.Add(new Label { Text = title, AutoSize = true, Margin = new Padding(0, 8, 8, 8) }, 0, row);
            input.Dock = DockStyle.Top; input.Margin = new Padding(0, 5, 0, 8); table.Controls.Add(input, 1, row);
        }

        private static Control Browse(TextBox input, bool directory, string filter)
        {
            var panel = new TableLayoutPanel { ColumnCount = 2, Height = 32, Margin = Padding.Empty };
            panel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100)); panel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 45));
            input.Dock = DockStyle.Fill; panel.Controls.Add(input);
            var button = new Button { Text = "...", Dock = DockStyle.Fill };
            button.Click += delegate {
                if (directory) { using (var dialog = new FolderBrowserDialog()) { dialog.Description = "Escolha uma pasta vazia para a aplicação"; if (dialog.ShowDialog() == DialogResult.OK) input.Text = dialog.SelectedPath; } }
                else { using (var dialog = new OpenFileDialog()) { dialog.Filter = filter; if (dialog.ShowDialog() == DialogResult.OK) input.Text = dialog.FileName; } }
            };
            panel.Controls.Add(button); return panel;
        }

        private void AddDestination()
        {
            var table = Page("1. Requisitos");
            Note(table, "Este instalador destina-se a um servidor Windows já preparado com PHP 8.3+ de 64 bits, MySQL e Apache ou IIS com HTTPS. Inclui a aplicação e as bibliotecas: não precisa de Composer no destino. Não instala nem substitui serviços do servidor.");
            target.Text = @"C:\Sites\AnferaltaDocs"; php.Text = FindPhp(); url.Text = "https://documentos.entidade.pt";
            Field(table, "Pasta nova da aplicação", Browse(target, true, ""));
            Field(table, "Executável PHP (php.exe)", Browse(php, false, "PHP|php.exe"));
            Field(table, "URL HTTPS da aplicação", url);
            Note(table, "A pasta deve estar vazia. Instalações concluídas não são substituídas. A conta que executa este assistente precisa de escrever no destino; use a elevação do Windows se a política dessa pasta o exigir.");
        }

        private void AddDatabase()
        {
            var table = Page("2. Base de dados");
            Note(table, "Indique uma base MySQL vazia e uma conta dedicada com os privilégios de instalação. Peça estes dados ao administrador da infraestrutura. Não é utilizada uma conta root nem são apagadas tabelas existentes.");
            databaseHost.Text = "127.0.0.1"; databasePort.Text = "3306"; databaseName.Text = "documentos"; databaseUser.Text = "documentos_instalacao";
            Field(table, "Servidor MySQL", databaseHost); Field(table, "Porta", databasePort); Field(table, "Base vazia", databaseName); Field(table, "Utilizador de instalação", databaseUser);
            databasePassword.UseSystemPasswordChar = true; Field(table, "Password MySQL", databasePassword);
            Note(table, "Depois de instalar, atribua ao processo web uma conta MySQL com privilégios de aplicação. Reserve os privilégios de instalação e backup para a manutenção.");
        }

        private void AddAdministrator()
        {
            var table = Page("3. Administrador");
            Note(table, "Este será o primeiro administrador da entidade. A password não é apresentada no resumo nem passada em argumentos de processo.");
            administratorName.Text = "Administrador";
            Field(table, "Nome", administratorName); Field(table, "Email", administrator);
            administratorPassword.UseSystemPasswordChar = true; administratorConfirmation.UseSystemPasswordChar = true;
            Field(table, "Password inicial", administratorPassword); Field(table, "Confirmar password", administratorConfirmation);
            Note(table, "Use pelo menos 14 caracteres. O instalador guarda a password inicial num ficheiro privado separado da chave de recuperação. Altere-a no perfil após o primeiro acesso.");
        }

        private void AddMail()
        {
            var table = Page("4. Email");
            enableMail.Text = "Configurar e testar SMTP agora (sem enviar email)"; enableMail.AutoSize = true; Field(table, "Configuração opcional", enableMail);
            mailPort.Text = "465"; mailEncryption.DropDownStyle = ComboBoxStyle.DropDownList; mailEncryption.Items.AddRange(new object[] { "smtps", "tls" }); mailEncryption.SelectedIndex = 0;
            mailPassword.UseSystemPasswordChar = true;
            Field(table, "Servidor SMTP", mailHost); Field(table, "Porta", mailPort); Field(table, "Encriptação", mailEncryption); Field(table, "Utilizador", mailUser); Field(table, "Password SMTP", mailPassword); Field(table, "Email do remetente", mailFrom); Field(table, "Ficheiro CA (opcional)", Browse(mailCa, false, "Certificados CA|*.pem;*.crt|Todos os ficheiros|*.*"));
            enableMail.CheckedChanged += delegate { foreach (Control item in new Control[] { mailHost, mailPort, mailEncryption, mailUser, mailPassword, mailFrom, mailCa }) item.Enabled = enableMail.Checked; };
            foreach (Control item in new Control[] { mailHost, mailPort, mailEncryption, mailUser, mailPassword, mailFrom, mailCa }) item.Enabled = false;
        }

        private void AddExecution()
        {
            var table = Page("5. Instalar");
            summary.Multiline = true; summary.ReadOnly = true; summary.Height = 115; summary.ScrollBars = ScrollBars.Vertical;
            Field(table, "Resumo sem passwords", summary);
            progress.Height = 18; progress.Style = ProgressBarStyle.Continuous; Field(table, "Progresso", progress);
            log.Multiline = true; log.ReadOnly = true; log.ScrollBars = ScrollBars.Vertical; log.Height = 205; log.Font = new Font("Consolas", 9F);
            int row = table.RowCount++; table.Controls.Add(log, 0, row); table.SetColumnSpan(log, 2); log.Dock = DockStyle.Top;
        }

        internal static string FindPhp()
        {
            foreach (string folder in (Environment.GetEnvironmentVariable("PATH") ?? "").Split(';')) { try { string found = Path.Combine(folder.Trim('"'), "php.exe"); if (File.Exists(found)) return found; } catch { } }
            string wamp = @"C:\wamp\bin\php";
            if (Directory.Exists(wamp)) foreach (string folder in Directory.GetDirectories(wamp).OrderByDescending(x => x)) if (File.Exists(Path.Combine(folder, "php.exe"))) return Path.Combine(folder, "php.exe");
            return "";
        }

        private void UpdateSummary()
        {
            summary.Text = "Destino: " + target.Text + Environment.NewLine + "URL: " + url.Text + Environment.NewLine + "MySQL: " + databaseHost.Text + ":" + databasePort.Text + " / " + databaseName.Text + Environment.NewLine + "Administrador: " + administrator.Text + Environment.NewLine + "SMTP: " + (enableMail.Checked ? "configurar e testar" : "configurar posteriormente");
        }

        private void ValidateInput()
        {
            if (!File.Exists(php.Text) || !String.Equals(Path.GetFileName(php.Text), "php.exe", StringComparison.OrdinalIgnoreCase)) throw new InvalidOperationException("Escolha o executável php.exe do servidor.");
            string root = Path.GetFullPath(target.Text.Trim());
            if (String.Equals(root.TrimEnd('\\'), Path.GetPathRoot(root).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase)) throw new InvalidOperationException("Escolha uma pasta própria, não a raiz da unidade.");
            Uri address;
            if (!Uri.TryCreate(url.Text.Trim(), UriKind.Absolute, out address) || address.Scheme != "https" || address.UserInfo.Length > 0 || address.Query.Length > 0 || address.Fragment.Length > 0 || address.AbsolutePath != "/") throw new InvalidOperationException("Indique uma URL HTTPS na raiz do domínio.");
            int port;
            if (!Int32.TryParse(databasePort.Text, out port) || port < 1 || port > 65535 || !Regex.IsMatch(databaseName.Text, "^[A-Za-z0-9_]+$") || String.IsNullOrWhiteSpace(databaseHost.Text) || String.IsNullOrWhiteSpace(databaseUser.Text) || databaseUser.Text.Trim().Equals("root", StringComparison.OrdinalIgnoreCase) || databasePassword.Text.Length == 0) throw new InvalidOperationException("Reveja a ligação MySQL: base vazia, porta válida e utilizador dedicado com password.");
            if (String.IsNullOrWhiteSpace(administratorName.Text) || !ValidEmail(administrator.Text) || administratorPassword.Text.Length < 14 || administratorPassword.Text != administratorConfirmation.Text) throw new InvalidOperationException("Reveja o administrador: nome, email e passwords iguais com pelo menos 14 caracteres.");
            if (enableMail.Checked && (String.IsNullOrWhiteSpace(mailHost.Text) || String.IsNullOrWhiteSpace(mailUser.Text) || mailPassword.Text.Length == 0 || !ValidEmail(mailFrom.Text) || !Int32.TryParse(mailPort.Text, out port) || port < 1 || port > 65535 || (mailCa.Text.Length > 0 && !File.Exists(mailCa.Text)))) throw new InvalidOperationException("Reveja a configuração SMTP e o ficheiro CA opcional.");
        }

        private static bool ValidEmail(string value) { try { return new System.Net.Mail.MailAddress(value.Trim()).Address == value.Trim(); } catch { return false; } }

        private async void Next(object sender, EventArgs arguments)
        {
            if (completed) { Close(); return; }
            if (pages.SelectedIndex < 4) { pages.SelectedIndex++; return; }
            try { ValidateInput(); } catch (Exception error) { MessageBox.Show(this, error.Message, "Rever dados", MessageBoxButtons.OK, MessageBoxIcon.Information); return; }
            string destination = Path.GetFullPath(target.Text.Trim()), phpExe = Path.GetFullPath(php.Text);
            string temp = Path.Combine(Path.GetTempPath(), "AnferaltaDocs-Setup-" + Guid.NewGuid().ToString("N"));
            var data = new Dictionary<string, object> { { "APP_URL", url.Text.Trim().TrimEnd('/') }, { "APP_TIMEZONE", "Europe/Lisbon" }, { "DB_HOST", databaseHost.Text.Trim() }, { "DB_PORT", Int32.Parse(databasePort.Text) }, { "DB_NAME", databaseName.Text }, { "DB_USER", databaseUser.Text.Trim() }, { "ADMIN_EMAIL", administrator.Text.Trim() }, { "ADMIN_NAME", administratorName.Text.Trim() } };
            string dbSecret = databasePassword.Text, adminSecret = administratorPassword.Text, smtpSecret = mailPassword.Text;
            secretValues = new string[] { dbSecret, adminSecret, smtpSecret }.Where(x => x.Length > 0).ToArray();
            bool useMail = enableMail.Checked;
            if (useMail) { data["MAIL_HOST"] = mailHost.Text.Trim(); data["MAIL_PORT"] = Int32.Parse(mailPort.Text); data["MAIL_ENCRYPTION"] = mailEncryption.Text; data["MAIL_USER"] = mailUser.Text.Trim(); data["MAIL_FROM"] = mailFrom.Text.Trim(); data["MAIL_FROM_NAME"] = "Gestão Documental"; data["MAIL_CA_FILE"] = mailCa.Text; }
            busy = true; next.Enabled = false; back.Enabled = false; pages.Enabled = false; progress.Style = ProgressBarStyle.Marquee;
            try
            {
                await Task.Run(delegate {
                    if (Directory.Exists(destination) && Directory.EnumerateFileSystemEntries(destination).Any()) Package.VerifyExisting(destination); else Package.Extract(destination, AppendLog);
                    AppendLog("A verificar o PHP e as extensões...");
                    if (RunPhp(phpExe, destination, new string[] { "install.php", "--check" }, 120000) != 0) throw new InvalidOperationException("O PHP não satisfaz os requisitos. Ative as extensões indicadas e repita o assistente.");
                    Package.SecureDirectory(temp);
                    WriteSecret(Path.Combine(temp, "database.txt"), dbSecret); WriteSecret(Path.Combine(temp, "administrator.txt"), adminSecret);
                    data["DB_PASS_FILE"] = Path.Combine(temp, "database.txt"); data["ADMIN_PASSWORD_FILE"] = Path.Combine(temp, "administrator.txt");
                    if (useMail) { WriteSecret(Path.Combine(temp, "smtp.txt"), smtpSecret); data["MAIL_PASS_FILE"] = Path.Combine(temp, "smtp.txt"); }
                    string config = Path.Combine(temp, "install.json"); File.WriteAllText(config, new JavaScriptSerializer().Serialize(data), new UTF8Encoding(false));
                    bool resume = File.Exists(Path.Combine(destination, "storage", ".installation-incomplete"));
                    var planArguments = new List<string> { "install.php", "--config=" + config, "--plan" }; if (resume) planArguments.Add("--resume");
                    AppendLog("A validar a configuração e a base de dados...");
                    if (RunPhp(phpExe, destination, planArguments, 120000) != 0) throw new InvalidOperationException("A validação recusou a instalação. Reveja os dados e as mensagens acima. Nenhuma base existente foi apagada.");
                    var installArguments = new List<string> { "install.php", "--config=" + config, "--yes" }; if (resume) installArguments.Add("--resume");
                    AppendLog(resume ? "A retomar a instalação registada..." : "A aplicar o esquema e criar o administrador...");
                    if (RunPhp(phpExe, destination, installArguments, 0) != 0) throw new InvalidOperationException("A instalação não terminou. Os ficheiros e o estado foram preservados para retoma. Não apague as marcas de manutenção.");
                    completed = true;
                    if (useMail) {
                        AppendLog("A verificar TLS e autenticação SMTP, sem enviar email...");
                        try { if (RunPhp(phpExe, destination, new string[] { "cli/smtp-check.php" }, 120000) != 0) AppendLog("A aplicação está instalada. A configuração SMTP precisa de revisão antes de usar email."); }
                        catch (Exception) { AppendLog("A aplicação está instalada. Não foi possível verificar SMTP; reveja a ligação e a configuração antes de usar email."); }
                    }
                    foreach (string file in Directory.GetFiles(temp)) File.Delete(file); Directory.Delete(temp);
                    AppendLog("\r\nINSTALAÇÃO CONCLUÍDA.\r\nConfigure o servidor HTTPS com DocumentRoot em: " + Path.Combine(destination, "public") + "\r\nAjuste a conta/grupo do PHP, valide o domínio e crie um backup completo antes de abrir o serviço.\r\nGuia: " + Path.Combine(destination, "docs", "guia-instalacao.md") + "\r\nChave de recuperação: secrets/recovery.key (guardar cópia fora do servidor).\r\nA password inicial está em secrets/initial-admin-password.txt; altere-a no primeiro acesso.");
                });
                MessageBox.Show(this, "A aplicação foi instalada. Termine a configuração do servidor HTTPS e as verificações descritas no guia. Não foram enviados emails.", "AnferaltaDocs", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch (Exception error) { AppendLog("\r\n" + error.Message + "\r\nSe existirem ficheiros de entrada em " + temp + ", mantenha-os privados até resolver a instalação."); MessageBox.Show(this, Redact(error.Message), completed ? "Aplicação instalada; rever passos complementares" : "Instalação não concluída", MessageBoxButtons.OK, MessageBoxIcon.Warning); }
            finally { busy = false; next.Enabled = true; back.Enabled = !completed; pages.Enabled = !completed; progress.Style = ProgressBarStyle.Continuous; progress.Value = completed ? 100 : 0; next.Text = completed ? "Fechar" : "Tentar novamente"; databasePassword.Clear(); administratorPassword.Clear(); administratorConfirmation.Clear(); mailPassword.Clear(); secretValues = new string[0]; }
        }

        private static void WriteSecret(string path, string value) { using (var output = new FileStream(path, FileMode.CreateNew, FileAccess.Write, FileShare.None)) { byte[] bytes = new UTF8Encoding(false).GetBytes(value); output.Write(bytes, 0, bytes.Length); } }

        private int RunPhp(string executable, string directory, IEnumerable<string> args, int timeout)
        {
            var start = new ProcessStartInfo(executable, String.Join(" ", args.Select(Package.QuoteArgument))) { WorkingDirectory = directory, UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true, RedirectStandardError = true, StandardOutputEncoding = Encoding.UTF8, StandardErrorEncoding = Encoding.UTF8 };
            foreach (string key in start.EnvironmentVariables.Keys.Cast<string>().ToArray()) if (new string[] { "DB_", "MAIL_", "APP_", "BACKUP_", "SESSION_" }.Any(prefix => key.StartsWith(prefix, StringComparison.OrdinalIgnoreCase)) || key == "ADMIN_EMAILS" || key == "SLA_REPORT_EMAILS") start.EnvironmentVariables.Remove(key);
            using (var process = new Process { StartInfo = start })
            {
                process.OutputDataReceived += delegate(object sender, DataReceivedEventArgs item) { if (item.Data != null) AppendLog(item.Data); };
                process.ErrorDataReceived += delegate(object sender, DataReceivedEventArgs item) { if (item.Data != null) AppendLog(item.Data); };
                process.Start(); process.BeginOutputReadLine(); process.BeginErrorReadLine();
                if (timeout > 0 && !process.WaitForExit(timeout)) { process.Kill(); process.WaitForExit(); throw new TimeoutException("A verificação excedeu o tempo limite. Confirme a ligação e os requisitos do servidor."); }
                process.WaitForExit(); return process.ExitCode;
            }
        }

        private string Redact(string value) { foreach (string secret in secretValues) value = value.Replace(secret, "[protegido]"); return value; }
        private void AppendLog(string text)
        {
            string safe = Redact(text);
            if (IsDisposed) return;
            if (InvokeRequired) { BeginInvoke(new Action<string>(AppendSafeLog), safe); return; }
            AppendSafeLog(safe);
        }
        private void AppendSafeLog(string text) { if (!IsDisposed) log.AppendText(text + Environment.NewLine); }
    }

    internal static class SelfTest
    {
        internal static int Run(string destination)
        {
            int checks = 0; var failed = new List<string>();
            Action<bool, string> verify = delegate(bool ok, string label) { checks++; if (!ok) failed.Add(label); };
            string root = Path.GetFullPath(destination);
            if (Directory.Exists(root) && Directory.EnumerateFileSystemEntries(root).Any()) return 2;
            Directory.CreateDirectory(root);
            try
            {
                foreach (string path in new string[] { "../escape", "/absolute", "C:/drive", "dir\\file", "dir/CON.txt", "dir/file.", "dir//file" })
                {
                    bool rejected = false; try { Package.SafePath(root, path); } catch (InvalidDataException) { rejected = true; } verify(rejected, "Reject " + path);
                }
                verify(Package.SafePath(root, "App/Core/Env.php").StartsWith(root), "Contained extraction path");
                verify(Package.QuoteArgument(@"C:\folder with spaces\php.exe") == "\"C:\\folder with spaces\\php.exe\"", "Windows quoting");
                string app = Path.Combine(root, "application"); Package.Extract(app, delegate { });
                verify(File.Exists(Path.Combine(app, "install.php")), "Installer included");
                verify(File.Exists(Path.Combine(app, "vendor", "autoload.php")), "Dependencies included");
                verify(File.Exists(Path.Combine(app, "docs", "guia-instalacao.md")), "Guide included");
                verify(!File.Exists(Path.Combine(app, ".env")) && !Directory.Exists(Path.Combine(app, "secrets")) && !Directory.Exists(Path.Combine(app, "backups")) && !Directory.Exists(Path.Combine(app, "storage")), "No operational data or secrets");
                string php = Wizard.FindPhp();
                if (File.Exists(php)) {
                    var start = new ProcessStartInfo(php, Package.QuoteArgument(Path.Combine(app, "install.php")) + " --check") { WorkingDirectory = app, UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true, RedirectStandardError = true };
                    using (var process = Process.Start(start)) {
                        string output = process.StandardOutput.ReadToEnd(); process.StandardError.ReadToEnd(); process.WaitForExit();
                        verify(process.ExitCode == 0 && output.Contains("[OK] vendor/autoload.php"), "Bundled application preflight runs successfully");
                    }
                } else verify(false, "PHP needed for executable integration test");
                // Exercise resume checks on a compact fixture; every real payload
                // file has already been hashed by Extract, so do not rescan 2,500 files.
                string probe = Path.Combine(root, "resume-probe"); Directory.CreateDirectory(probe);
                string probeFile = Path.Combine(probe, "install.php"); File.WriteAllText(probeFile, "fixture");
                File.WriteAllText(Path.Combine(probe, ".setup-package.json"), new JavaScriptSerializer().Serialize(new Dictionary<string, string> { { "install.php", Package.HashFile(probeFile) } }));
                Package.VerifyExisting(probe); verify(true, "Extracted manifest matches");
                File.AppendAllText(probeFile, "changed"); bool altered = false; try { Package.VerifyExisting(probe); } catch (IOException) { altered = true; } verify(altered, "Modified code is refused");
                using (var wizard = new Wizard()) { verify(wizard.Text.Contains("AnferaltaDocs"), "Wizard constructs"); }
            }
            catch (Exception error) { failed.Add(error.GetType().Name + ": " + error.Message); }
            File.WriteAllText(Path.Combine(root, "self-test.json"), new JavaScriptSerializer().Serialize(new { checks = checks, failures = failed }), new UTF8Encoding(false));
            return failed.Count == 0 ? 0 : 1;
        }
    }
}
