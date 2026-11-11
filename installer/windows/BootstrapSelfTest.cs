using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Text;
using System.Web.Script.Serialization;

namespace AnferaltaDocsSetup
{
    internal static class BootstrapSelfTest
    {
        internal static int Run(string report)
        {
            var checks=new List<object>(); var failures=new List<string>();
            Action<bool,string> verify=delegate(bool valid,string name) { checks.Add(new { name=name, passed=valid }); if(!valid) failures.Add(name); };
            try {
                foreach(string host in new[] { "docs.empresa.pt", "DOCS.EMPRESA.LOCAL", "arquivo.intranet.empresa" }) verify(CleanMachineWizard.ValidateDomain(host)==host.ToLowerInvariant(),"Accept corporate DNS: "+host);
                foreach(string host in new[] { "https://docs.empresa.pt", "localhost", "127.0.0.1", "docs.empresa.pt/path", "docs.empresa.pt:443", "*.empresa.pt", "docs.empresa.pt\r\nInjected: value", "-docs.empresa.pt" }) {
                    bool refused=false; try { CleanMachineWizard.ValidateDomain(host); } catch(ArgumentException) { refused=true; } verify(refused,"Reject invalid DNS: "+host.Replace("\r\n","[CRLF]"));
                }
                verify(CleanMachineWizard.ValidateNetworks("LocalSubnet")=="LocalSubnet","Local subnet scope");
                verify(CleanMachineWizard.ValidateNetworks("10.20.0.0/16, 192.168.5.0/24")=="10.20.0.0/16,192.168.5.0/24","Corporate CIDR scope");
                foreach(string scope in new[] { "Any", "0.0.0.0/0", "10.0.0.0/99", "LocalSubnet; command", "", "192.168.0.1" }) {
                    bool refused=false; try { CleanMachineWizard.ValidateNetworks(scope); } catch(ArgumentException) { refused=true; } verify(refused,"Reject unrestricted or invalid scope: "+scope);
                }
                var serializer=new JavaScriptSerializer();
                using(var stream=Assembly.GetExecutingAssembly().GetManifestResourceStream("AnferaltaDocs.Bootstrap.runtimes.json"))
                using(var reader=new StreamReader(stream,Encoding.UTF8)) {
                    var catalog=serializer.Deserialize<Dictionary<string,Dictionary<string,string>>>(reader.ReadToEnd());
                    verify(catalog.Keys.OrderBy(x=>x).SequenceEqual(new[] { "mysql", "php", "rewrite", "vc" }),"Complete runtime catalog");
                    foreach(var item in catalog) {
                        Uri url=new Uri(item.Value["url"]);
                        verify(url.Scheme=="https" && new[] { "cdn.mysql.com", "downloads.php.net", "download.microsoft.com", "download.visualstudio.microsoft.com" }.Contains(url.Host) && System.Text.RegularExpressions.Regex.IsMatch(item.Value["sha256"],"^[a-f0-9]{64}$"),"Official HTTPS runtime and pinned SHA256: "+item.Key);
                    }
                }
                foreach(string name in CleanMachineWizard.Resources) using(var stream=Assembly.GetExecutingAssembly().GetManifestResourceStream("AnferaltaDocs.Bootstrap."+name)) verify(stream!=null && stream.Length>0,"Bootstrap resource: "+name);
                using(var wizard=new CleanMachineWizard()) verify(wizard.Text.Contains("raiz"),"Clean-machine wizard constructs");
            } catch(Exception error) { failures.Add(error.GetType().Name+": "+error.Message); }
            File.WriteAllText(report,serializerResult(checks,failures),new UTF8Encoding(false));
            return failures.Count==0?0:1;
        }
        private static string serializerResult(List<object> checks,List<string> failures) { return new JavaScriptSerializer().Serialize(new { checks=checks,failures=failures,system_services_modified=false }); }
    }
}
