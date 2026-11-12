using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;

namespace AnferaltaDocsSetup
{
    internal sealed class CleanMachineWizard : Form
    {
        private readonly TextBox destination = new TextBox(), domain = new TextBox(), email = new TextBox(), administrator = new TextBox(), password = new TextBox(), confirmation = new TextBox(), pfx = new TextBox(), pfxPassword = new TextBox(), networks = new TextBox();
        private readonly TextBox log = new TextBox();
        private readonly Button install = new Button();
        private readonly TableLayoutPanel fields = new TableLayoutPanel();
        private bool busy;
        internal static readonly string[] Resources = { "Test-CleanMachine.ps1", "Provision.ps1", "CertificatePolicy.cs", "bootstrap-database.php", "runtimes.json" };

        internal CleanMachineWizard()
        {
            Text = "AnferaltaDocs — Instalação Windows de raiz (candidato de validação)";
            ClientSize = new Size(900, 820); MinimumSize = new Size(820, 780); StartPosition = FormStartPosition.CenterScreen;
            Font = new Font("Segoe UI", 10); BackColor = Color.White;
            var introduction = new Label { Dock = DockStyle.Top, Height = 115, Padding = new Padding(20, 12, 20, 0), Text = "Instalação na rede da empresa\nPrepara IIS, PHP, MySQL, HTTPS e AnferaltaDocs numa máquina Windows nova.\nRequer administrador, Internet, domínio DNS e certificado PFX válido.\nCandidato de validação: ensaiar numa máquina virtual limpa antes de produção." };
            Controls.Add(introduction);
            fields.Location = new Point(20, 122); fields.Size = new Size(855, 420); fields.Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right; fields.ColumnCount = 2;
            fields.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 250)); fields.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
            destination.Text = @"C:\AnferaltaDocs"; administrator.Text = "Administrador"; networks.Text = "LocalSubnet";
            password.UseSystemPasswordChar = true; confirmation.UseSystemPasswordChar = true; pfxPassword.UseSystemPasswordChar = true;
            AddField("Pasta nova (ou retoma própria)", destination); AddField("Domínio, sem https://", domain); AddField("Redes autorizadas (CIDR ou LocalSubnet)", networks);
            AddField("Nome do administrador", administrator); AddField("Email do administrador", email); AddField("Password inicial (14+ caracteres)", password); AddField("Confirmar password", confirmation);
            var browse = new TableLayoutPanel { ColumnCount = 2, Height = 32, Dock = DockStyle.Fill };
            browse.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100)); browse.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 90));
            pfx.Dock = DockStyle.Fill; browse.Controls.Add(pfx);
            var choose = new Button { Text = "Escolher", Dock = DockStyle.Fill };
            choose.Click += delegate { using (var dialog = new OpenFileDialog { Filter = "Certificado com chave privada|*.pfx;*.p12" }) { if (dialog.ShowDialog(this) == DialogResult.OK) pfx.Text = dialog.FileName; } };
            browse.Controls.Add(choose); AddField("Certificado HTTPS (.pfx)", browse); AddField("Password do certificado", pfxPassword);
            Controls.Add(fields);
            install.Text = "Verificar e instalar"; install.Location = new Point(660, 555); install.Size = new Size(210, 38); install.Anchor = AnchorStyles.Top | AnchorStyles.Right; install.Click += Install;
            Controls.Add(install);
            log.Multiline = true; log.ReadOnly = true; log.ScrollBars = ScrollBars.Vertical; log.Location = new Point(20, 610); log.Size = new Size(850, 185); log.Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right;
            Controls.Add(log);
            FormClosing += delegate(object sender, FormClosingEventArgs e) { if (busy) { e.Cancel = true; MessageBox.Show(this, "Aguarde a conclusão da etapa. O Windows não será reiniciado automaticamente."); } };
        }
        private void AddField(string title, Control control)
        {
            int row = fields.RowCount++; fields.RowStyles.Add(new RowStyle(SizeType.Absolute, 44));
            fields.Controls.Add(new Label { Text = title, AutoSize = true, Margin = new Padding(0, 8, 8, 0) }, 0, row);
            control.Dock = DockStyle.Fill; control.Margin = new Padding(0, 4, 0, 5); fields.Controls.Add(control, 1, row);
        }
        internal static string ValidateDomain(string value)
        {
            string host = value.Trim().ToLowerInvariant();
            if (host.Length > 253 || !host.Contains(".") || host == "localhost" || Uri.CheckHostName(host) != UriHostNameType.Dns || !Regex.IsMatch(host, @"^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$")) throw new ArgumentException("Indique o nome DNS completo, sem protocolo, porta ou caminho.");
            return host;
        }
        internal static string ValidateNetworks(string value)
        {
            if (value.Trim() == "LocalSubnet") return "LocalSubnet";
            var result = new List<string>();
            foreach (string part in value.Split(',')) {
                string item=part.Trim(); string[] pieces=item.Split('/'); System.Net.IPAddress address; int prefix;
                if (pieces.Length != 2 || !System.Net.IPAddress.TryParse(pieces[0], out address) || !Int32.TryParse(pieces[1], out prefix) || prefix < 1 || prefix > (address.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork ? 32 : 128)) throw new ArgumentException("Indique LocalSubnet ou redes CIDR separadas por vírgulas (por exemplo, 10.20.0.0/16).");
                result.Add(address.ToString()+"/"+prefix);
            }
            return String.Join(",", result);
        }
        internal static void Protect(string path)
        {
            Directory.CreateDirectory(path);
            var acl=new DirectorySecurity(); acl.SetAccessRuleProtection(true,false);
            foreach (var sid in new[] { WellKnownSidType.BuiltinAdministratorsSid, WellKnownSidType.LocalSystemSid }) acl.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(sid,null),FileSystemRights.FullControl,InheritanceFlags.ContainerInherit|InheritanceFlags.ObjectInherit,PropagationFlags.None,AccessControlType.Allow));
            new DirectoryInfo(path).SetAccessControl(acl);
        }
        internal static void ExtractResources(string path)
        {
            foreach (string name in Resources) {
                using (Stream input=Assembly.GetExecutingAssembly().GetManifestResourceStream("AnferaltaDocs.Bootstrap."+name)) {
                    if (input==null) throw new InvalidDataException("Componente do instalador em falta: "+name);
                    string file=Path.Combine(path,name);
                    using (var memory=new MemoryStream()) { input.CopyTo(memory); byte[] content=memory.ToArray();
                        if (File.Exists(file) && !File.ReadAllBytes(file).SequenceEqual(content)) throw new IOException("Um componente da instalação foi alterado. Reveja a pasta bootstrap antes de retomar.");
                        if (!File.Exists(file)) File.WriteAllBytes(file,content);
                    }
                }
            }
        }
        private async void Install(object sender, EventArgs e)
        {
            Dictionary<string,string> config;
            string root, host, adminPassword, certPassword, certFile;
            try {
                if (!new WindowsPrincipal(WindowsIdentity.GetCurrent()).IsInRole(WindowsBuiltInRole.Administrator)) throw new InvalidOperationException("Execute o EXE como administrador para instalar os serviços do Windows.");
                root=Path.GetFullPath(destination.Text.Trim()).TrimEnd('\\');
                if (!Regex.IsMatch(root,@"^[A-Za-z]:\\[^\x00-\x1f]+$") || root.IndexOfAny(new[] {'"','\r','\n'})>=0) throw new ArgumentException("Escolha uma subpasta local para a instalação.");
                host=ValidateDomain(domain.Text);
                string remote=ValidateNetworks(networks.Text);
                var address=new System.Net.Mail.MailAddress(email.Text.Trim());
                if (address.Address != email.Text.Trim() || String.IsNullOrWhiteSpace(administrator.Text)) throw new ArgumentException("Indique o nome e um email válido do administrador.");
                if (administrator.Text.Any(Char.IsControl)) throw new ArgumentException("O nome do administrador contém caracteres de controlo.");
                adminPassword=password.Text; certPassword=pfxPassword.Text; certFile=Path.GetFullPath(pfx.Text);
                if (adminPassword.Length<14 || adminPassword!=confirmation.Text || adminPassword.Any(Char.IsControl)) throw new ArgumentException("A password inicial deve ter pelo menos 14 caracteres, sem controlos, e coincidir com a confirmação.");
                if (!File.Exists(certFile) || new FileInfo(certFile).Length>10*1024*1024) throw new ArgumentException("Escolha um ficheiro PFX válido, até 10 MB.");
                config=new Dictionary<string,string> { {"Root",root},{"Domain",host},{"Networks",remote},{"Email",address.Address.ToLowerInvariant()},{"Administrator",administrator.Text.Trim()} };
            } catch (Exception error) { MessageBox.Show(this,error.Message,"Verificação inicial"); return; }
            busy=true; fields.Enabled=false; install.Enabled=false; log.Clear();
            try {
                int result=await Task.Run(delegate { return Provision(config,adminPassword,certFile,certPassword); });
                if (result==3010) Append("Reinicie o Windows quando for conveniente e execute novamente este EXE com a mesma pasta e dados.");
                else if (result!=0) throw new InvalidOperationException("A preparação foi interrompida. Consulte a etapa indicada no registo e retome apenas a mesma instalação.");
                else { Append("Concluído. Configure o DNS da empresa para o IP deste servidor e teste o acesso noutra máquina."); install.Text="Instalação concluída"; }
            } catch (Exception error) { Append(error.Message); }
            finally { busy=false; fields.Enabled=true; install.Enabled=install.Text!="Instalação concluída"; password.Clear(); confirmation.Clear(); pfxPassword.Clear(); }
        }
        private int Provision(Dictionary<string,string> config, string adminPassword, string certFile, string certPassword)
        {
            var serializer=new JavaScriptSerializer(); string root=config["Root"], folder=Path.Combine(root,"bootstrap"), marker=Path.Combine(root,".bootstrap-owner.json"), app=Path.Combine(root,"app");
            Append("A validar o certificado, o domínio e a cadeia de confiança do Windows...");
            using (var cert=CertificatePolicy.Validate(certFile,certPassword,config["Domain"])) { config["Certificate"]=cert.Thumbprint; }
            bool resume=File.Exists(marker);
            if (resume) {
                Package.CheckParents(root);
                var owner=serializer.Deserialize<Dictionary<string,string>>(File.ReadAllText(marker));
                if (config.Any(item => !owner.ContainsKey(item.Key) || owner[item.Key]!=item.Value)) throw new IOException("Para retomar, indique a mesma pasta, domínio, redes, administrador e certificado.");
                string state=Path.Combine(folder,"state.json");
                if (File.Exists(state) && serializer.Deserialize<Dictionary<string,string>>(File.ReadAllText(state)).ContainsKey("complete")) throw new IOException("Esta instalação já foi concluída. Use os procedimentos de manutenção.");
                if (!File.Exists(Path.Combine(folder,"admin-password.txt")) || File.ReadAllText(Path.Combine(folder,"admin-password.txt"))!=adminPassword) throw new IOException("A password inicial deve coincidir com a usada na instalação interrompida.");
                ExtractResources(folder);
                var resumeCheck=RunPowerShell(Path.Combine(folder,"Test-CleanMachine.ps1"),"-Destination "+Package.QuoteArgument(root)+" -Resume",null);
                if (resumeCheck.Item1!=0 || !(bool)serializer.Deserialize<Dictionary<string,object>>(resumeCheck.Item2)["ready"]) throw new IOException("A retoma exige Windows compatível, elevação, espaço livre e nenhum reinício pendente. Conclua o reinício antes de retomar.");
                Append("A verificar os ficheiros da instalação interrompida...");
            } else {
                string probe=Path.Combine(Path.GetTempPath(),"AnferaltaDocs-Preflight-"+Guid.NewGuid().ToString("N"));
                Package.SecureDirectory(probe); ExtractResources(probe);
                var check=RunPowerShell(Path.Combine(probe,"Test-CleanMachine.ps1"),"-Destination "+Package.QuoteArgument(root),null);
                if (check.Item1!=0) throw new IOException("Não foi possível concluir o diagnóstico inicial. Nenhum serviço foi alterado.");
                var report=serializer.Deserialize<Dictionary<string,object>>(check.Item2);
                if (!(bool)report["ready"]) {
                    Append(check.Item2);
                    throw new IOException("A máquina não cumpre os requisitos de preparação de raiz. Corrija as verificações antes de instalar.");
                }
                Package.CheckParents(root); Protect(root); Protect(folder);
                File.WriteAllText(marker,serializer.Serialize(config),new UTF8Encoding(false));
                File.WriteAllText(Path.Combine(folder,"admin-password.txt"),adminPassword,new UTF8Encoding(false));
            }
            ExtractResources(folder);
            if (File.Exists(Path.Combine(app,".setup-package.json"))) Package.VerifyExisting(app,true);
            else Package.Extract(app,Append,resume);
            string copiedCert=Path.Combine(folder,"server.pfx");
            if (File.Exists(copiedCert) && Package.HashFile(copiedCert)!=Package.HashFile(certFile)) throw new IOException("O certificado PFX difere do usado na instalação interrompida.");
            if (!File.Exists(copiedCert)) File.Copy(certFile,copiedCert);
            File.WriteAllText(Path.Combine(folder,"pfx-password.txt"),certPassword,new UTF8Encoding(false));
            File.WriteAllText(Path.Combine(folder,"input.json"),serializer.Serialize(config),new UTF8Encoding(false));
            var result=RunPowerShell(Path.Combine(folder,"Provision.ps1"),"-Config "+Package.QuoteArgument(Path.Combine(folder,"input.json")),Append);
            return result.Item1;
        }
        internal static Tuple<int,string> RunPowerShell(string script,string arguments,Action<string> output)
        {
            string shell=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows),@"System32\WindowsPowerShell\v1.0\powershell.exe");
            var info=new ProcessStartInfo(shell,"-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "+Package.QuoteArgument(script)+" "+arguments) { UseShellExecute=false,CreateNoWindow=true,RedirectStandardOutput=true,RedirectStandardError=true,StandardOutputEncoding=new UTF8Encoding(false),StandardErrorEncoding=new UTF8Encoding(false) };
            var text=new StringBuilder(); var errors=new StringBuilder();
            using (var process=new Process { StartInfo=info }) {
                process.OutputDataReceived+=delegate(object sender,DataReceivedEventArgs e) { if(e.Data!=null) { text.AppendLine(e.Data); if(output!=null) output(e.Data); } };
                process.ErrorDataReceived+=delegate(object sender,DataReceivedEventArgs e) { if(e.Data!=null) { errors.AppendLine(e.Data); if(output!=null) output("O Windows comunicou um erro; consulte o diagnóstico privado da etapa."); } };
                process.Start(); process.BeginOutputReadLine(); process.BeginErrorReadLine(); process.WaitForExit();
                if(errors.Length>0) File.WriteAllText(Path.Combine(Path.GetDirectoryName(script),"powershell-error.txt"),errors.ToString(),new UTF8Encoding(false));
                return Tuple.Create(process.ExitCode,text.ToString());
            }
        }
        private void Append(string message) { if(IsDisposed)return; if(InvokeRequired) { BeginInvoke(new Action<string>(Append),message); return; } log.AppendText(message+Environment.NewLine); }
    }
}
