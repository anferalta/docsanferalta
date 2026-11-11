using System;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Linq;

namespace AnferaltaDocsSetup
{
    public static class CertificatePolicy
    {
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct SSLPolicy { public uint size; public uint authType; public uint checks; [MarshalAs(UnmanagedType.LPWStr)] public string server; }
        [StructLayout(LayoutKind.Sequential)]
        private struct PolicyParameters { public uint size; public uint flags; public IntPtr extra; }
        [StructLayout(LayoutKind.Sequential)]
        private struct PolicyStatus { public uint size; public uint error; public int chainIndex; public int elementIndex; public IntPtr extra; }
        [DllImport("crypt32.dll", SetLastError = true)]
        private static extern bool CertVerifyCertificateChainPolicy(IntPtr policy, IntPtr chain, ref PolicyParameters parameters, ref PolicyStatus status);

        // Uses Windows SSL hostname policy, online revocation and existing machine trust.
        // Never installs a root CA or accepts an untrusted/self-signed certificate.
        public static X509Certificate2 Validate(string path, string password, string hostname)
        {
            var collection = new X509Certificate2Collection();
            collection.Import(path, password, (X509KeyStorageFlags)32); // EphemeralKeySet; .NET 4.7.2+
            foreach (X509Certificate2 leaf in collection)
            {
                if (!leaf.HasPrivateKey || !leaf.Extensions.Cast<X509Extension>().Any(extension => extension.Oid.Value == "2.5.29.17")) continue;
                using (var chain = new X509Chain(true))
                {
                    chain.ChainPolicy.ExtraStore.AddRange(collection);
                    chain.ChainPolicy.ApplicationPolicy.Add(new Oid("1.3.6.1.5.5.7.3.1"));
                    chain.ChainPolicy.RevocationMode = X509RevocationMode.Online;
                    chain.ChainPolicy.RevocationFlag = X509RevocationFlag.ExcludeRoot;
                    chain.ChainPolicy.UrlRetrievalTimeout = TimeSpan.FromSeconds(20);
                    if (!chain.Build(leaf)) continue;
                    var ssl = new SSLPolicy { size = (uint)Marshal.SizeOf(typeof(SSLPolicy)), authType = 2, checks = 0, server = hostname };
                    IntPtr extra = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(SSLPolicy)));
                    try
                    {
                        Marshal.StructureToPtr(ssl, extra, false);
                        var parameters = new PolicyParameters { size = (uint)Marshal.SizeOf(typeof(PolicyParameters)), extra = extra };
                        var status = new PolicyStatus { size = (uint)Marshal.SizeOf(typeof(PolicyStatus)) };
                        if (CertVerifyCertificateChainPolicy(new IntPtr(4), chain.ChainContext, ref parameters, ref status) && status.error == 0) {
                            foreach (X509Certificate2 other in collection) if (!Object.ReferenceEquals(other,leaf)) other.Dispose();
                            return leaf;
                        }
                    }
                    finally { Marshal.DestroyStructure(extra, typeof(SSLPolicy)); Marshal.FreeHGlobal(extra); }
                }
            }
            foreach (X509Certificate2 cert in collection) cert.Dispose();
            throw new InvalidOperationException("O certificado precisa de chave privada, cadeia de confiança válida, revogação verificável e nome DNS correspondente ao domínio.");
        }
    }
}
